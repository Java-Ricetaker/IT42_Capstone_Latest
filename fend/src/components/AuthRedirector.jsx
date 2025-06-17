import { useEffect, useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import api from '../api/api';
import LoadingSpinner from '../components/LoadingSpinner'; // ✅ adjust path if needed

export default function AuthRedirector() {
  const [checking, setChecking] = useState(true); // 🔁 controls spinner
  const navigate = useNavigate();
  const location = useLocation();

  useEffect(() => {
    const checkSession = async () => {
      try {
        const res = await api.get('/api/user');
        const user = res.data;

        const publicRoutes = ['/', '/login', '/register', '/forgot-password'];
        if (publicRoutes.includes(location.pathname)) {
          if (user.role === 'admin') navigate('/admin');
          else if (user.role === 'staff') navigate('/staff');
          else if (user.role === 'patient') navigate('/patient');
        }
      } catch (err) {
        // Not logged in
      } finally {
        setChecking(false); // 🟢 finish check
      }
    };

    checkSession();
  }, [location.pathname, navigate]);

  if (checking) {
    return <LoadingSpinner message="Loading..." />;
  }

  return null;
}
