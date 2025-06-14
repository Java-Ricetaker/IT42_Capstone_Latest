import { Outlet, useNavigate } from "react-router-dom";
import api from "../api/api";
import './AdminLayout.css'; 

function AdminLayout() {
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
        className="bg-dark text-white p-3"
        style={{ width: "250px", minHeight: "100vh" }}
      >
        <h4>Admin Panel</h4>
        <ul className="nav flex-column">
          <li className="nav-item">
            <a className="nav-link text-white" href="/admin">
              Dashboard
            </a>
          </li>
          <li className="nav-item">
            <a className="nav-link text-white" href="/admin/device-approvals">
              Device Approvals
            </a>
          </li>
          <li className="nav-item">
            <a className="nav-link text-white" href="/admin/approved-devices">
              Approved Devices
            </a>
          </li>
          <li className="nav-item">
            <a className="nav-link text-white" href="/admin/staff-register">
              Create Staff Account
            </a>
          </li>
          <li className="nav-item mt-4">
            <button
              className="btn btn-outline-danger w-100"
              onClick={handleLogout}
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

export default AdminLayout;
