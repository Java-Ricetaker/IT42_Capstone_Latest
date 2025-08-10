import { Outlet, useNavigate, NavLink } from "react-router-dom";
import api from "../api/api";
import "./StaffLayout.css";

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
      <div
        className="bg-light p-3 border-end"
        style={{ width: "220px", minHeight: "100vh" }}
      >
        <h5>Staff Menu</h5>
        <ul className="nav flex-column">
          <li className="nav-item">
            <NavLink
              to="/staff"
              end
              className={({ isActive }) =>
                "nav-link" + (isActive ? " fw-bold text-primary" : "")
              }
            >
              🏠 Dashboard
            </NavLink>
          </li>

          <li className="nav-item">
            <NavLink
              to="/staff/appointments"
              className={({ isActive }) =>
                "nav-link" + (isActive ? " fw-bold text-primary" : "")
              }
            >
              📅 Appointments
            </NavLink>
          </li>

          <li className="nav-item">
            <NavLink
              to="/staff/appointment-reminders"
              className={({ isActive }) =>
                "nav-link" + (isActive ? " fw-bold text-primary" : "")
              }
            >
              🔔 Reminders
            </NavLink>
          </li>

          <li className="nav-item">
            <NavLink
              to="/staff/profile"
              className={({ isActive }) =>
                "nav-link" + (isActive ? " fw-bold text-primary" : "")
              }
            >
              👤 Account
            </NavLink>
          </li>

          <li className="nav-item mt-4">
            <button
              onClick={handleLogout}
              className="btn btn-outline-danger w-100"
            >
              🚪 Logout
            </button>
          </li>
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
