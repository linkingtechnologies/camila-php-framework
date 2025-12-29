/* global fetch */
(function (global) {
  "use strict";

  function WorkTableClient(options) {
    options = options || {};

    var baseUrl = options.baseUrl || "";
    var recordsPath = options.recordsPath || "/records";

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

    // Example: http://.../cf_api.php + /records -> http://.../cf_api.php/records
    var base = joinUrl(baseUrl, recordsPath);

    /* ==========================
       utils
       ========================== */

    function encode(value) {
      return encodeURIComponent(String(value));
    }

    function buildQuery(params) {
      if (!params) return "";

      var sp = new URLSearchParams();

      // AND filters
      if (params.filters && params.filters.length) {
        params.filters.forEach(function (f) {
          sp.append("filter", f.join(","));
        });
      }

      // OR filters: filter1, filter2...
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
          Array.isArray(params.include) ? params.include.join(",") : params.include
        );
      }

      if (params.exclude) {
        sp.set(
          "exclude",
          Array.isArray(params.exclude) ? params.exclude.join(",") : params.exclude
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
          Array.isArray(params.page) ? params.page.join(",") : params.page
        );
      }

      var qs = sp.toString();
      return qs ? "?" + qs : "";
    }

    function request(path, method, body) {
      var controller = new AbortController();
      var timer = setTimeout(function () {
        controller.abort();
      }, timeoutMs);

      var headers = {};

      // API key header
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

    /* ==========================
       table-bound API
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
        }
      };
    }

    /* ==========================
       public API
       ========================== */

    return {
      table: tableApi,

      // direct access (optional)
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

      // helpers
      filter: function (column, operator) {
        var values = Array.prototype.slice.call(arguments, 2);
        return [column, operator].concat(values);
      },

      negate: function (operator) {
        return "n" + operator;
      }
    };
  }

  // expose globally
  global.WorkTableClient = WorkTableClient;

})(window);
