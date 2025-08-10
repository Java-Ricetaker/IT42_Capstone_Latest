import { Link, useNavigate } from "react-router-dom";
import { useEffect, useState } from "react";
import api from "../api/api";

function PatientNavbar() {
  const navigate = useNavigate();
  const [user, setUser] = useState(null);

  useEffect(() => {
    api
      .get("/api/user")
      .then((res) => setUser(res.data))
      .catch(() => setUser(null));
  }, []);

  const handleLogout = async () => {
    try {
      await api.post("/api/logout");
      navigate("/login");
    } catch (err) {
      console.error("Logout failed", err);
    }
  };

  return (
    <nav className="navbar navbar-expand-lg navbar-light bg-light shadow-sm px-3">
      <Link className="navbar-brand" to="/">
        🦷 DCMS
      </Link>

      <div className="ms-auto d-flex align-items-center gap-3">
        <Link to="/patient/appointment" className="nav-link">
          📅 Book
        </Link>
        <Link to="/patient/appointments" className="nav-link">
          📜 Appointments
        </Link>
        <Link to="/patient/profile" className="nav-link">
          👤 Profile
        </Link>

        {user && (
          <button
            onClick={handleLogout}
            className="btn btn-outline-danger btn-sm"
          >
            Logout
          </button>
        )}
      </div>
    </nav>
  );
}

export default PatientNavbar;
