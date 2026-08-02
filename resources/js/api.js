import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('depot_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

api.interceptors.response.use(
    (r) => r,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('depot_token');
            if (!window.location.pathname.startsWith('/login') && !window.location.pathname.startsWith('/install')) {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    },
);

export default api;
