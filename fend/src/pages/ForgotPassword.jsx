import { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../api/api';
import AuthLayout from '../layouts/AuthLayout';
import LoadingSpinner from '../components/LoadingSpinner';

function ForgotPassword() {
  const [email, setEmail] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setMessage('');
    setError('');

    try {
      setLoading(true);
      await api.get('/sanctum/csrf-cookie');

      const res = await api.post('/forgot-password', {
        email: email,
      });

      setMessage(res.data.message || 'Reset link sent to your email.');
    } catch (err) {
      const msg = err.response?.data?.message || 'Something went wrong';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <AuthLayout>
      {loading && <LoadingSpinner message="Sending reset link..." />}
      <div className="card shadow-sm p-4" style={{ width: '100%', maxWidth: '500px' }}>
        <h3 className="text-center mb-4">Forgot Password</h3>

        <form onSubmit={handleSubmit}>
          <div className="mb-3">
            <label className="form-label">
              <i className="bi bi-envelope me-2" />
              Email Address
            </label>
            <input
              type="email"
              className="form-control"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              autoComplete="email"
            />
          </div>

          <button type="submit" className="btn btn-primary w-100">
            <i className="bi bi-send me-2" />
            Send Reset Link
          </button>
        </form>

        {message && <div className="alert alert-success text-center mt-3">{message}</div>}
        {error && <div className="alert alert-danger text-center mt-3">{error}</div>}

        <div className="text-center mt-3">
          <Link to="/login" className="d-block text-decoration-none text-primary">
            <i className="bi bi-arrow-left me-2" />
            Back to Login
          </Link>
        </div>
      </div>
    </AuthLayout>
  );
}

export default ForgotPassword;
