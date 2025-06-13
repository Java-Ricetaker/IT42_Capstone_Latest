import { BrowserRouter, Routes, Route } from "react-router-dom";

// Auth pages
import Login from "../pages/Login";
import Register from "../pages/Register";
import ForgotPassword from "../pages/ForgotPassword";
import ResetPassword from "../pages/ResetPassword";
import VerifyEmail from "../pages/VerifyEmail";
import VerifySuccess from "../pages/VerifySuccess";

// Admin layout and pages
import AdminLayout from "../layouts/AdminLayout";
import AdminDashboard from "../pages/Admin/Dashboard";
import AdminDeviceApprovals from "../pages/Admin/DeviceApprovals";
import AdminApprovedDevices from "../pages/Admin/ApprovedDevices";
import AdminStaffRegister from "../pages/Admin/StaffRegister";

// Staff layout and pages
import StaffLayout from "../layouts/StaffLayout";
import StaffDashboard from "../pages/Staff/StaffDashboard";


export default function AppRouter() {
  return (
    <BrowserRouter>
      <Routes>
        {/* Public / Auth Routes */}
        <Route path="/" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/forgot-password" element={<ForgotPassword />} />
        <Route path="/password-reset/:token" element={<ResetPassword />} />
        <Route path="/verify-email" element={<VerifyEmail />} />
        <Route path="/verify-success" element={<VerifySuccess />} />

        {/* Admin Routes */}
        <Route path="/admin" element={<AdminLayout />}>
          <Route index element={<AdminDashboard />} />
          <Route path="device-approvals" element={<AdminDeviceApprovals />} />
          <Route path="approved-devices" element={<AdminApprovedDevices />} />
          <Route path="staff-register" element={<AdminStaffRegister />} />
        </Route>

        {/* Staff Routes */}
        <Route path="/staff" element={<StaffLayout />}>
          <Route index element={<StaffDashboard />} />
          {/* Add more staff routes as needed */}
        </Route>
      </Routes>
    </BrowserRouter>
  );
}
