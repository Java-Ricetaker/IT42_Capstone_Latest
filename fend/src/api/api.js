import axios from 'axios';

const api = axios.create({
  baseURL: 'http://127.0.0.1:8000', // ✅ Match exactly with browser origin
  withCredentials: true,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
  }
});

// 🩹 Inject X-XSRF-TOKEN manually from cookie if needed
api.interceptors.request.use(config => {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  if (match) {
    config.headers['X-XSRF-TOKEN'] = decodeURIComponent(match[1]);
  }
  return config;
});

api.logout = () => api.post('/logout');

export default api;
