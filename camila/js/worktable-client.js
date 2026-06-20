/* global fetch */
/**
 * WorkTableClient
 * ===============
 *
 * Lightweight JavaScript (browser) client that wraps REST endpoints of the form:
 *
 *   /records/<table>                      (GET list, POST create)
 *   /records/<table>/<id>                 (GET read, PUT update, DELETE remove)
 *   /records/<table>/distinct/<column>    (GET distinct values)
 *   /columns/<table>                      (GET describe table columns)
 *   /permissions/<table>                  (GET table permissions)
 *   /sequence/<table>                     (GET next sequence id for table)
 *   /tables                               (GET list of visible tables)
 *   /attachments/<table>/<id>             (POST upload image, GET serve binary, HEAD exists, DELETE remove)
 *   /attachments/<table>                  (GET list of IDs with an attachment)
 *
 * Endpoints marked with [optional] require the corresponding controller/middleware
 * to be active on the server side.
 *
 * Features:
 * - CRUD + list operations on records
 * - AND / OR filter chains
 * - Column include / exclude
 * - Sorting, pagination, size limiting
 * - Request timeout via AbortController
 * - Optional API key header
 * - Table schema introspection (/columns)
 * - Table-level permissions (/permissions)
 * - Sequence ID generation (/sequence)
 * - Available tables enumeration (/tables)
 * - Image attachments (/attachments)
 *   - Upload: multipart/form-data, field "file", image/* only
 *   - Serve:  GET triggers download (Content-Disposition: attachment); use fetchAttachment() for blob URL
 *   - Exists: HEAD check, no body transferred
 *   - List:   IDs with an attachment for a given table
 *   - Delete: removes binary + metadata from server storage
 *
 * Public methods:
 *   table(name)                           returns a table-bound API object with:
 *     .list(query)  .create(data)  .read(id)  .update(id,patch)  .remove(id)
 *     .describe(query)  .permissions(query)  .sequence(query)  .distinct(col,query)
 *     .uploadAttachment(id,file)  .attachmentUrl(id)  .fetchAttachment(id)  .hasAttachment(id)  .listAttachments()  .deleteAttachment(id)
 *   create(table, data)
 *   read(table, id)
 *   update(table, id, patch)
 *   remove(table, id)
 *   list(table, query)
 *   describe(table, query)
 *   permissions(table, query)
 *   sequence(table, query)
 *   distinct(table, column, query)
 *   tables()
 *   uploadAttachment(table, id, file)     POST multipart → Promise<{url}>
 *   attachmentUrl(table, id)              returns URL string (triggers download)
 *   fetchAttachment(table, id)            GET → Promise<{blob, mime, ext}>
 *   hasAttachment(table, id)             HEAD → Promise<false|{mime,ext}>
 *   listAttachments(table)               GET → Promise<{ids: Array<{id,mime,ext}>>}
 *   deleteAttachment(table, id)           DELETE → Promise<void>
 *   filter(column, operator, ...values)
 *   negate(operator)
 */
(function (global) {
  "use strict";

  /**
   * @param {Object} [options]
   * @param {string} [options.baseUrl=""] Base API URL prefix
   * @param {string} [options.recordsPath="/records"] Records endpoint path
   * @param {string} [options.columnsPath="/columns"] Columns endpoint path
   * @param {string} [options.permissionsPath="/permissions"] Permissions endpoint path
   * @param {string} [options.sequencePath="/sequence"] Sequence endpoint path
   * @param {string} [options.tablesPath="/tables"] Tables list endpoint path
   * @param {string} [options.attachmentsPath="/attachments"] Attachments endpoint path
   * @param {string|null} [options.apiKeyHeaderName=null] API key header name
   * @param {string|null} [options.apiKeyHeaderValue=null] API key header value
   * @param {number} [options.timeoutMs=20000] Request timeout in ms
   */
  function WorkTableClient(options) {
    options = options || {};

    var baseUrl = options.baseUrl || "";
    var recordsPath = options.recordsPath || "/records";
    var columnsPath = options.columnsPath || "/columns";
    var permissionsPath = options.permissionsPath || "/permissions";
    var sequencePath = options.sequencePath || "/sequence";
    var tablesPath = options.tablesPath || "/tables";
    var attachmentsPath = options.attachmentsPath || "/attachments";
    var apiKeyHeaderName = options.apiKeyHeaderName || null;
    var apiKeyHeaderValue = options.apiKeyHeaderValue || null;
    var timeoutMs = options.timeoutMs || 20000;

    function joinUrl(a, b) {
      a = String(a || "");
      b = String(b || "");
      if (!a) return b;
      if (!b) return a;

      var aEnds = a.charAt(a.length - 1) === "/";
      var bStarts = b.charAt(0) === "/";

      if (aEnds && bStarts) return a + b.slice(1);
      if (!aEnds && !bStarts) return a + "/" + b;
      return a + b;
    }

    // KEEP EXACT BEHAVIOR for records base
    var base = joinUrl(baseUrl, recordsPath);

    // base for columns describe
    var baseCols = joinUrl(baseUrl, columnsPath);

    // base for permissions
    var basePerms = joinUrl(baseUrl, permissionsPath);

    // base for sequence
    var baseSeq = joinUrl(baseUrl, sequencePath);

    // base for tables list
    var baseTables = joinUrl(baseUrl, tablesPath);

    // base for attachments
    var baseAttachments = joinUrl(baseUrl, attachmentsPath);

    /* ==========================
       Utilities
       ========================== */

    function encode(value) {
      return encodeURIComponent(String(value));
    }

    /**
     * Builds a query string from a ListQuery object.
     *
     * @param {Object} [params]
     * @returns {string}
     */
    function buildQuery(params) {
      if (!params) return "";

      var sp = new URLSearchParams();

      // AND filters
      if (params.filters && params.filters.length) {
        params.filters.forEach(function (f) {
          sp.append("filter", f.join(","));
        });
      }

      // OR filters (filter1, filter2, ...)
      if (params.orFilters) {
        Object.keys(params.orFilters).forEach(function (key) {
          var group = params.orFilters[key];
          if (group && group.join) {
            sp.append(key, group.join(","));
          }
        });
      }

      // include / exclude
      if (params.include) {
        sp.set(
          "include",
          Array.isArray(params.include)
            ? params.include.join(",")
            : params.include
        );
      }

      if (params.exclude) {
        sp.set(
          "exclude",
          Array.isArray(params.exclude)
            ? params.exclude.join(",")
            : params.exclude
        );
      }

      // order
      if (params.order) {
        if (Array.isArray(params.order)) {
          params.order.forEach(function (o) {
            sp.append("order", o.join(","));
          });
        } else {
          sp.append("order", params.order);
        }
      }

      // size
      if (params.size !== undefined) {
        sp.set("size", params.size);
      }

      // page
      if (params.page !== undefined) {
        sp.set(
          "page",
          Array.isArray(params.page)
            ? params.page.join(",")
            : params.page
        );
      }

      var qs = sp.toString();
      return qs ? "?" + qs : "";
    }

    /**
     * Executes a fetch request with timeout and JSON handling.
     * (RETROCOMPAT: signature and behavior kept the same)
     *
     * @param {string} path
     * @param {"GET"|"POST"|"PUT"|"DELETE"} method
     * @param {Object} [body]
     * @returns {Promise<any>}
     */
    function request(path, method, body) {
      var controller = new AbortController();
      var timer = setTimeout(function () {
        controller.abort();
      }, timeoutMs);

      var headers = {};

      if (apiKeyHeaderName && apiKeyHeaderValue) {
        headers[apiKeyHeaderName] = apiKeyHeaderValue;
      }

      var reqOptions = {
        method: method,
        headers: headers,
        signal: controller.signal
      };

      if (body !== undefined && body !== null) {
        headers["Content-Type"] = "application/json";
        reqOptions.body = JSON.stringify(body);
      }

      return fetch(base + path, reqOptions)
        .then(function (res) {
          var ct = res.headers.get("content-type") || "";
          var reader =
            ct.indexOf("application/json") !== -1 ? res.json() : res.text();

          return reader.then(function (payload) {
            if (!res.ok) {
              var err = new Error("HTTP " + res.status);
              err.status = res.status;
              err.payload = payload;
              throw err;
            }
            return payload;
          });
        })
        .finally(function () {
          clearTimeout(timer);
        });
    }

    /**
     * Same as request(), but targets /columns base.
     */
    function requestColumns(path, method, body) {
      var controller = new AbortController();
      var timer = setTimeout(function () {
        controller.abort();
      }, timeoutMs);

      var headers = {};

      if (apiKeyHeaderName && apiKeyHeaderValue) {
        headers[apiKeyHeaderName] = apiKeyHeaderValue;
      }

      var reqOptions = {
        method: method,
        headers: headers,
        signal: controller.signal
      };

      if (body !== undefined && body !== null) {
        headers["Content-Type"] = "application/json";
        reqOptions.body = JSON.stringify(body);
      }

      return fetch(baseCols + path, reqOptions)
        .then(function (res) {
          var ct = res.headers.get("content-type") || "";
          var reader =
            ct.indexOf("application/json") !== -1 ? res.json() : res.text();

          return reader.then(function (payload) {
            if (!res.ok) {
              var err = new Error("HTTP " + res.status);
              err.status = res.status;
              err.payload = payload;
              throw err;
            }
            return payload;
          });
        })
        .finally(function () {
          clearTimeout(timer);
        });
    }

    /**
     * Same as request(), but targets /permissions base.
     */
    function requestPermissions(path, method, body) {
      var controller = new AbortController();
      var timer = setTimeout(function () {
        controller.abort();
      }, timeoutMs);

      var headers = {};

      if (apiKeyHeaderName && apiKeyHeaderValue) {
        headers[apiKeyHeaderName] = apiKeyHeaderValue;
      }

      var reqOptions = {
        method: method,
        headers: headers,
        signal: controller.signal
      };

      if (body !== undefined && body !== null) {
        headers["Content-Type"] = "application/json";
        reqOptions.body = JSON.stringify(body);
      }

      return fetch(basePerms + path, reqOptions)
        .then(function (res) {
          var ct = res.headers.get("content-type") || "";
          var reader =
            ct.indexOf("application/json") !== -1 ? res.json() : res.text();

          return reader.then(function (payload) {
            if (!res.ok) {
              var err = new Error("HTTP " + res.status);
              err.status = res.status;
              err.payload = payload;
              throw err;
            }
            return payload;
          });
        })
        .finally(function () {
          clearTimeout(timer);
        });
    }

    /**
     * Same as request(), but targets /sequence base.
     */
    function requestSequence(path, method, body) {
      var controller = new AbortController();
      var timer = setTimeout(function () {
        controller.abort();
      }, timeoutMs);

      var headers = {};

      if (apiKeyHeaderName && apiKeyHeaderValue) {
        headers[apiKeyHeaderName] = apiKeyHeaderValue;
      }

      var reqOptions = {
        method: method,
        headers: headers,
        signal: controller.signal
      };

      if (body !== undefined && body !== null) {
        headers["Content-Type"] = "application/json";
        reqOptions.body = JSON.stringify(body);
      }

      return fetch(baseSeq + path, reqOptions)
        .then(function (res) {
          var ct = res.headers.get("content-type") || "";
          var reader =
            ct.indexOf("application/json") !== -1 ? res.json() : res.text();

          return reader.then(function (payload) {
            if (!res.ok) {
              var err = new Error("HTTP " + res.status);
              err.status = res.status;
              err.payload = payload;
              throw err;
            }
            return payload;
          });
        })
        .finally(function () {
          clearTimeout(timer);
        });
    }

    /* ==========================
       Table-bound API
       ========================== */

    function tableApi(tableName) {
      var t = encode(tableName);

      return {
        list: function (query) {
          return request("/" + t + buildQuery(query), "GET");
        },

        create: function (data) {
          return request("/" + t, "POST", data);
        },

        read: function (id) {
          return request("/" + t + "/" + encode(id), "GET");
        },

        update: function (id, patch) {
          return request("/" + t + "/" + encode(id), "PUT", patch);
        },

        remove: function (id) {
          return request("/" + t + "/" + encode(id), "DELETE");
        },

        describe: function (query) {
          return requestColumns("/" + t + buildQuery(query), "GET");
        },

        permissions: function (query) {
          return requestPermissions("/" + t + buildQuery(query), "GET");
        },

        sequence: function (query) {
          return requestSequence("/" + t + buildQuery(query), "GET");
        },

        distinct: function (column, query) {
          return request(
            "/" + t + "/distinct/" + encode(column) + buildQuery(query),
            "GET"
          );
        },

        uploadAttachment: function (id, file) {
          var controller = new AbortController();
          var timer = setTimeout(function () { controller.abort(); }, timeoutMs);
          var headers = {};
          if (apiKeyHeaderName && apiKeyHeaderValue) {
            headers[apiKeyHeaderName] = apiKeyHeaderValue;
          }
          var fd = new FormData();
          fd.append("file", file);
          var url = baseAttachments + "/" + t + "/" + encode(id);
          return fetch(url, { method: "POST", headers: headers, body: fd, signal: controller.signal })
            .then(function (res) {
              return res.json().then(function (payload) {
                if (!res.ok) {
                  var err = new Error("HTTP " + res.status);
                  err.status = res.status; err.payload = payload;
                  throw err;
                }
                return payload;
              });
            })
            .finally(function () { clearTimeout(timer); });
        },

        attachmentUrl: function (id) {
          return baseAttachments + "/" + t + "/" + encode(id);
        },

        hasAttachment: function (id) {
          var controller = new AbortController();
          var timer = setTimeout(function () { controller.abort(); }, timeoutMs);
          var headers = {};
          if (apiKeyHeaderName && apiKeyHeaderValue) {
            headers[apiKeyHeaderName] = apiKeyHeaderValue;
          }
          var url = baseAttachments + "/" + t + "/" + encode(id);
          return fetch(url, { method: "HEAD", headers: headers, signal: controller.signal })
            .then(function (res) {
              if (res.status !== 200) { return false; }
              var mime = res.headers.get("Content-Type") || "";
              var ext  = res.headers.get("X-Attachment-Ext") || "";
              return { mime: mime, ext: ext };
            })
            .finally(function () { clearTimeout(timer); });
        },

        listAttachments: function () {
          var controller = new AbortController();
          var timer = setTimeout(function () { controller.abort(); }, timeoutMs);
          var headers = {};
          if (apiKeyHeaderName && apiKeyHeaderValue) {
            headers[apiKeyHeaderName] = apiKeyHeaderValue;
          }
          var url = baseAttachments + "/" + t;
          return fetch(url, { method: "GET", headers: headers, signal: controller.signal })
            .then(function (res) {
              return res.json().then(function (payload) {
                if (!res.ok) {
                  var err = new Error("HTTP " + res.status);
                  err.status = res.status; err.payload = payload;
                  throw err;
                }
                return payload;
              });
            })
            .finally(function () { clearTimeout(timer); });
        },

        fetchAttachment: function (id) {
          var controller = new AbortController();
          var timer = setTimeout(function () { controller.abort(); }, timeoutMs);
          var headers = {};
          if (apiKeyHeaderName && apiKeyHeaderValue) {
            headers[apiKeyHeaderName] = apiKeyHeaderValue;
          }
          var url = baseAttachments + "/" + t + "/" + encode(id);
          return fetch(url, { method: "GET", headers: headers, signal: controller.signal })
            .then(function (res) {
              if (!res.ok) {
                var err = new Error("HTTP " + res.status);
                err.status = res.status;
                throw err;
              }
              var ext = res.headers.get("X-Attachment-Ext") || "";
              var mime = res.headers.get("Content-Type") || "";
              return res.blob().then(function (blob) {
                return { blob: blob, ext: ext, mime: mime };
              });
            })
            .finally(function () { clearTimeout(timer); });
        },

        deleteAttachment: function (id) {
          var controller = new AbortController();
          var timer = setTimeout(function () { controller.abort(); }, timeoutMs);
          var headers = {};
          if (apiKeyHeaderName && apiKeyHeaderValue) {
            headers[apiKeyHeaderName] = apiKeyHeaderValue;
          }
          var url = baseAttachments + "/" + t + "/" + encode(id);
          return fetch(url, { method: "DELETE", headers: headers, signal: controller.signal })
            .then(function (res) {
              if (!res.ok && res.status !== 204) {
                return res.text().then(function (body) {
                  var err = new Error("HTTP " + res.status);
                  err.status = res.status; err.payload = body;
                  throw err;
                });
              }
            })
            .finally(function () { clearTimeout(timer); });
        }
      };
    }

    /* ==========================
       Public API
       ========================== */

    return {
      table: tableApi,

      create: function (table, data) {
        return request("/" + encode(table), "POST", data);
      },

      read: function (table, id) {
        return request("/" + encode(table) + "/" + encode(id), "GET");
      },

      update: function (table, id, patch) {
        return request(
          "/" + encode(table) + "/" + encode(id),
          "PUT",
          patch
        );
      },

      remove: function (table, id) {
        return request("/" + encode(table) + "/" + encode(id), "DELETE");
      },

      list: function (table, query) {
        return request("/" + encode(table) + buildQuery(query), "GET");
      },

      describe: function (table, query) {
        return requestColumns("/" + encode(table) + buildQuery(query), "GET");
      },

      permissions: function (table, query) {
        return requestPermissions("/" + encode(table) + buildQuery(query), "GET");
      },

      sequence: function (table, query) {
        return requestSequence("/" + encode(table) + buildQuery(query), "GET");
      },

      distinct: function (table, column, query) {
        return request(
          "/" + encode(table) + "/distinct/" + encode(column) + buildQuery(query),
          "GET"
        );
      },

      tables: function () {
        var controller = new AbortController();
        var timer = setTimeout(function () { controller.abort(); }, timeoutMs);
        var headers = {};
        if (apiKeyHeaderName && apiKeyHeaderValue) {
          headers[apiKeyHeaderName] = apiKeyHeaderValue;
        }
        return fetch(baseTables, { method: "GET", headers: headers, signal: controller.signal })
          .then(function (res) {
            var ct = res.headers.get("content-type") || "";
            var reader = ct.indexOf("application/json") !== -1 ? res.json() : res.text();
            return reader.then(function (payload) {
              if (!res.ok) {
                var err = new Error("HTTP " + res.status);
                err.status = res.status;
                err.payload = payload;
                throw err;
              }
              return payload;
            });
          })
          .finally(function () { clearTimeout(timer); });
      },

      /**
       * Builds a filter tuple.
       * Example: filter("id","gt",1) -> ["id","gt",1]
       */
      filter: function (column, operator) {
        var values = Array.prototype.slice.call(arguments, 2);
        return [column, operator].concat(values);
      },

      /**
       * Negates an operator by prefixing "n".
       * Example: negate("eq") -> "neq"
       */
      negate: function (operator) {
        return "n" + operator;
      },

      /**
       * Uploads a file (image/*) as attachment for a record.
       * Sends multipart/form-data with field name "file".
       * @param {string} table
       * @param {string|number} id
       * @param {File} file  A browser File object
       * @returns {Promise<{url: string}>}
       */
      uploadAttachment: function (table, id, file) {
        var controller = new AbortController();
        var timer = setTimeout(function () { controller.abort(); }, timeoutMs);

        var headers = {};
        if (apiKeyHeaderName && apiKeyHeaderValue) {
          headers[apiKeyHeaderName] = apiKeyHeaderValue;
        }

        var formData = new FormData();
        formData.append("file", file);

        var url = baseAttachments + "/" + encode(table) + "/" + encode(id);
        return fetch(url, { method: "POST", headers: headers, body: formData, signal: controller.signal })
          .then(function (res) {
            return res.json().then(function (payload) {
              if (!res.ok) {
                var err = new Error("HTTP " + res.status);
                err.status = res.status;
                err.payload = payload;
                throw err;
              }
              return payload;
            });
          })
          .finally(function () { clearTimeout(timer); });
      },

      /**
       * Returns the URL to GET an attachment. The server sends Content-Disposition: attachment,
       * so the browser will download the file rather than display it inline.
       * Use fetchAttachment() to obtain a blob URL for inline display.
       * @param {string} table
       * @param {string|number} id
       * @returns {string}
       */
      attachmentUrl: function (table, id) {
        return baseAttachments + "/" + encode(table) + "/" + encode(id);
      },

      /**
       * Downloads an attachment and returns a blob URL suitable for inline display (e.g. <img src>).
       * @param {string} table
       * @param {string|number} id
       * @returns {Promise<{blob: Blob, mime: string, ext: string}>}
       */
      fetchAttachment: function (table, id) {
        var controller = new AbortController();
        var timer = setTimeout(function () { controller.abort(); }, timeoutMs);
        var headers = {};
        if (apiKeyHeaderName && apiKeyHeaderValue) {
          headers[apiKeyHeaderName] = apiKeyHeaderValue;
        }
        var url = baseAttachments + "/" + encode(table) + "/" + encode(id);
        return fetch(url, { method: "GET", headers: headers, signal: controller.signal })
          .then(function (res) {
            if (!res.ok) {
              var err = new Error("HTTP " + res.status);
              err.status = res.status;
              throw err;
            }
            var ext  = res.headers.get("X-Attachment-Ext") || "";
            var mime = res.headers.get("Content-Type") || "";
            return res.blob().then(function (blob) {
              return { blob: blob, mime: mime, ext: ext };
            });
          })
          .finally(function () { clearTimeout(timer); });
      },

      /**
       * Checks whether an attachment exists for a record (HEAD request).
       * Returns false if not found, or {mime, ext} if found.
       * @param {string} table
       * @param {string|number} id
       * @returns {Promise<false|{mime: string, ext: string}>}
       */
      hasAttachment: function (table, id) {
        var controller = new AbortController();
        var timer = setTimeout(function () { controller.abort(); }, timeoutMs);

        var headers = {};
        if (apiKeyHeaderName && apiKeyHeaderValue) {
          headers[apiKeyHeaderName] = apiKeyHeaderValue;
        }

        var url = baseAttachments + "/" + encode(table) + "/" + encode(id);
        return fetch(url, { method: "HEAD", headers: headers, signal: controller.signal })
          .then(function (res) {
            if (res.status !== 200) { return false; }
            var mime = res.headers.get("Content-Type") || "";
            var ext  = res.headers.get("X-Attachment-Ext") || "";
            return { mime: mime, ext: ext };
          })
          .finally(function () { clearTimeout(timer); });
      },

      /**
       * Returns the list of attachments for a table, each with id, mime and ext.
       * @param {string} table
       * @returns {Promise<{ids: Array<{id: string, mime: string, ext: string}>}>}
       */
      listAttachments: function (table) {
        var controller = new AbortController();
        var timer = setTimeout(function () { controller.abort(); }, timeoutMs);

        var headers = {};
        if (apiKeyHeaderName && apiKeyHeaderValue) {
          headers[apiKeyHeaderName] = apiKeyHeaderValue;
        }

        var url = baseAttachments + "/" + encode(table);
        return fetch(url, { method: "GET", headers: headers, signal: controller.signal })
          .then(function (res) {
            return res.json().then(function (payload) {
              if (!res.ok) {
                var err = new Error("HTTP " + res.status);
                err.status = res.status;
                err.payload = payload;
                throw err;
              }
              return payload;
            });
          })
          .finally(function () { clearTimeout(timer); });
      },

      /**
       * Deletes an attachment.
       * @param {string} table
       * @param {string|number} id
       * @returns {Promise<void>}
       */
      deleteAttachment: function (table, id) {
        var controller = new AbortController();
        var timer = setTimeout(function () { controller.abort(); }, timeoutMs);

        var headers = {};
        if (apiKeyHeaderName && apiKeyHeaderValue) {
          headers[apiKeyHeaderName] = apiKeyHeaderValue;
        }

        var url = baseAttachments + "/" + encode(table) + "/" + encode(id);
        return fetch(url, { method: "DELETE", headers: headers, signal: controller.signal })
          .then(function (res) {
            if (!res.ok && res.status !== 204) {
              return res.text().then(function (body) {
                var err = new Error("HTTP " + res.status);
                err.status = res.status;
                err.payload = body;
                throw err;
              });
            }
          })
          .finally(function () { clearTimeout(timer); });
      }
    };
  }

  // Expose globally
  global.WorkTableClient = WorkTableClient;

})(window);
