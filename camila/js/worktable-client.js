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
 *   /columns/<table>                      (GET describe table columns)     [optional extension]
 *   /permissions/<table>                  (GET table permissions)          [optional extension]
 *   /sequence/<table>                     (GET table sequence)             [optional extension]
 *
 * Features:
 * - CRUD + List operations
 * - AND / OR filters
 * - Column include / exclude
 * - Sorting, pagination, size limiting
 * - Request timeout via AbortController
 * - Optional API key header
 * - Optional table schema describe (/columns)
 * - Optional table permissions (/permissions)
 * - Optional table sequence (/sequence)
 * - Optional distinct values (/records/<table>/distinct/<column>)
 */
(function (global) {
  "use strict";

  /**
   * @param {Object} [options]
   * @param {string} [options.baseUrl=""] Base API URL
   * @param {string} [options.recordsPath="/records"] Records endpoint path
   * @param {string} [options.columnsPath="/columns"] Columns endpoint path (optional)
   * @param {string} [options.permissionsPath="/permissions"] Permissions endpoint path (optional)
   * @param {string} [options.sequencePath="/sequence"] Sequence endpoint path (optional)
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
      }
    };
  }

  // Expose globally
  global.WorkTableClient = WorkTableClient;

})(window);
