import { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../api/api';
import LoadingSpinner from '../components/LoadingSpinner';
import AuthLayout from '../layouts/AuthLayout';

function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);

  const handleLogin = async (e) => {
    e.preventDefault();

    try {
      setLoading(true);
      await api.get('/sanctum/csrf-cookie');
      const res = await api.post('/login', { email, password });

      localStorage.setItem('token', res.data.token);
      setMessage('Login successful!');
    } catch (err) {
      setMessage(err.response?.data?.message || 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <AuthLayout>
      {loading && <LoadingSpinner message="Logging in..." />}

      <div className="card shadow-sm p-4" style={{ width: '100%', maxWidth: '400px' }}>
        <h3 className="text-center mb-4">Login</h3>

        <form onSubmit={handleLogin}>
          <div className="mb-3">
            <label className="form-label">📧 Email</label>
            <input
              type="email"
              className="form-control"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="Email"
              required
              autoComplete="email"
            />
          </div>

          <div className="mb-3">
            <label className="form-label">🔒 Password</label>
            <input
              type="password"
              className="form-control"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="Password"
              required
              autoComplete="current-password"
            />
          </div>

          <button type="submit" className="btn btn-primary w-100">
            🔓 Login
          </button>
        </form>

        {message && <div className="alert alert-info text-center mt-3">{message}</div>}

        <div className="text-center mt-3">
          <Link to="/register" className="d-block mb-2 text-decoration-none text-primary">
            👤 Don't have an account? Register
          </Link>
          <Link to="/forgot-password" className="d-block text-danger text-decoration-none">
            ❓ Forgot Password?
          </Link>
        </div>
      </div>
    </AuthLayout>
  );
}

export default Login;
