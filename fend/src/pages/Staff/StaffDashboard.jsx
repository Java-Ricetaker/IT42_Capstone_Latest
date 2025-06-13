import { useEffect, useState } from "react";
import api from "../../api/api";

const StaffDashboard = () => {
  const [status, setStatus] = useState(null);

  useEffect(() => {
    const checkDevice = async () => {
      try {
        const res = await api.get("/api/device-status", {
          headers: {
            "X-Device-Fingerprint": localStorage.getItem("fingerprint"),
          },
        });
        setStatus(res.data);
      } catch (err) {
        console.error("Device check failed", err);
      }
    };

    checkDevice();
  }, []);

  if (!status) return <p>Loading dashboard...</p>;

  if (!status.approved) {
    return (
      <div className="alert alert-warning">
        🚫 This device is not yet approved. Please coordinate with your admin.
      </div>
    );
  }

  return (
    <div>
      <h2>Welcome, Staff Member!</h2>
      <p>✅ Your device is approved. You're now able to use the system.</p>
    </div>
  );
};

export default StaffDashboard;
