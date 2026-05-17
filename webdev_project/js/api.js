const appApi = {
  csrfToken: null,
  csrfPromise: null,

  async ensureCsrfToken() {
    if (this.csrfToken) {
      return this.csrfToken;
    }

    if (!this.csrfPromise) {
      this.csrfPromise = fetch("api/csrf.php", {
        credentials: "same-origin"
      })
        .then(async (response) => {
          const result = await response.json().catch(() => ({
            success: false,
            message: "Unexpected server response."
          }));

          if (!response.ok || result.success === false || !result.csrf_token) {
            throw new Error(result.message || "Unable to initialize security token.");
          }

          this.csrfToken = result.csrf_token;
          return this.csrfToken;
        })
        .finally(() => {
          this.csrfPromise = null;
        });
    }

    return this.csrfPromise;
  },

  async request(url, options = {}) {
    const method = (options.method || "GET").toUpperCase();
    const headers = {
      ...(options.headers || {})
    };

    if (options.body && !headers["Content-Type"]) {
      headers["Content-Type"] = "application/json";
    }

    if (!options.body) {
      delete headers["Content-Type"];
    }

    if (method !== "GET" && method !== "HEAD") {
      headers["X-CSRF-Token"] = await this.ensureCsrfToken();
    }

    const config = {
      credentials: "same-origin",
      ...options,
      method,
      headers
    };

    const response = await fetch(`api/${url}`, config);
    const result = await response.json().catch(() => ({
      success: false,
      message: "Unexpected server response."
    }));

    if (result.csrf_token) {
      this.csrfToken = result.csrf_token;
    }

    if (!response.ok || result.success === false) {
      throw new Error(result.message || "Request failed.");
    }

    return result;
  },

  get(url) {
    return this.request(url);
  },

  post(url, data = {}) {
    return this.request(url, {
      method: "POST",
      body: JSON.stringify(data)
    });
  }
};

window.appApi = appApi;
