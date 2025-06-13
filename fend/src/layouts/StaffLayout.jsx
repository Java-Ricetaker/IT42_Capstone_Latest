import { Outlet, useNavigate } from "react-router-dom";
import api from "../api/api";
import './StaffLayout.css';

function StaffLayout() {
  const navigate = useNavigate();

  const handleLogout = async () => {
    try {
      await api.post("/logout");
      localStorage.removeItem("token");
      navigate("/");
    } catch (err) {
      console.error("Logout failed", err);
    }
  };

  return (
    <div className="d-flex">
      {/* Sidebar */}
      <div className="bg-light p-3 border-end" style={{ width: "220px", minHeight: "100vh" }}>
        <h5>Staff Menu</h5>
        <ul className="nav flex-column">
          <li><a href="/staff" className="nav-link">🏠 Dashboard</a></li>
          <li><a href="/staff/profile" className="nav-link">👤 Account</a></li>
          <li><button onClick={handleLogout} className="btn btn-outline-danger w-100 mt-4">Logout</button></li>
        </ul>
      </div>

      {/* Main Content */}
      <div className="flex-grow-1 p-4">
        <Outlet />
      </div>
    </div>
  );
}

export default StaffLayout;
