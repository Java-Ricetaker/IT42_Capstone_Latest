import axios from 'axios';

const api = axios.create({
  baseURL: '', // same-origin; Laravel + SPA via ngrok
  withCredentials: true,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'ngrok-skip-browser-warning': 'true',
  }
});

// Inject XSRF token from cookie
api.interceptors.request.use(config => {
  const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  if (m) config.headers['X-XSRF-TOKEN'] = decodeURIComponent(m[1]);
  return config;
});

// Be gentle on 401s — don't kick users out for harmless probes
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const cfg = error.config || {};
    const status = error.response?.status;
    const url = cfg.url || '';

    // Ignore 401s when explicitly requested
    if (cfg.skip401Handler) {
      return Promise.reject(error);
    }

    // Ignore 401 from "who am I" style checks
    if (status === 401 && (url.includes('/api/user') || url.includes('/user'))) {
      return Promise.reject(error);
    }

    // Otherwise, send to login within SPA base (/app)
    if (status === 401) {
      try { localStorage.removeItem('token'); } catch {}
      window.location.href = '/app/login';
      return; // stop promise chain
    }

    return Promise.reject(error);
  }
);

api.logout = () => api.post('/logout');

export default api;
