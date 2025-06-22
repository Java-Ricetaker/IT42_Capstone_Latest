import axios from 'axios';

const api = axios.create({
  baseURL: 'https://d1db-149-30-138-246.ngrok-free.app',
  withCredentials: true,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
  },
});

api.defaults.headers.common['ngrok-skip-browser-warning'] = 'true';

api.interceptors.request.use(config => {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  if (match) {
    config.headers['X-XSRF-TOKEN'] = decodeURIComponent(match[1]);
  }
  return config;
});

api.interceptors.response.use(
  res => res,
  async error => {
    const config = error.config || {};

    if (error.response?.status === 419 && !config._retry) {
      config._retry = true;
      await api.get('/sanctum/csrf-cookie');
      return api(config);
    }

    if (error.response?.status === 401 && !config.skip401Handler) {
      localStorage.removeItem('token');
      window.location.href = '/';
    }

    return Promise.reject(error);
  }
);

api.logout = () => api.post('/logout');

export default api;
