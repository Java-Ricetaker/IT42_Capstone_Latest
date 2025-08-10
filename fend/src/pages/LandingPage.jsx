import { Link } from "react-router-dom";
import { useAuth } from "../hooks/useAuth";
import LoadingSpinner from "../components/LoadingSpinner";

function LandingPage() {
  const { user, authLoading } = useAuth();

  if (authLoading) {
    return <LoadingSpinner message="Checking session..." />;
  }

  if (user && user.role === 'patient') {
    return (
      <div className="container mt-5 text-center">
        <h2>Welcome, {user.name || "Patient"} 👋</h2>
        <p>Select an action below:</p>
        <Link to="/patient/appointment" className="btn btn-primary m-2">Book Appointment</Link>
        <Link to="/patient/history" className="btn btn-outline-secondary m-2">View History</Link>
        <Link to="/patient/profile" className="btn btn-outline-info m-2">Profile</Link>
      </div>
    );
  }

  return (
    <div className="container mt-5 text-center">
      <h2>Welcome to DCMS 🦷</h2>
      <p>Please login or register to continue.</p>
      <Link to="/login" className="btn btn-primary m-2">Login</Link>
      <Link to="/register" className="btn btn-outline-secondary m-2">Register</Link>
    </div>
  );
}

export default LandingPage;
