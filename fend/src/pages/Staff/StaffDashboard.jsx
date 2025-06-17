import { useEffect, useState } from "react";
import api from "../../api/api";
import { getFingerprint } from "../../utils/getFingerprint"; // ✅ import utility

const StaffDashboard = () => {
  const [status, setStatus] = useState(null);

  useEffect(() => {
    const checkDevice = async () => {
      try {
        const fingerprint = await getFingerprint(); // ✅ safely fetch or generate

        const res = await api.get("/api/device-status", {
          headers: {
            "X-Device-Fingerprint": fingerprint,
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
    const wasRejected = !status.temporary_code;

    return (
      <div className="alert alert-warning">
        {wasRejected ? (
          <>
            ❌ <strong>This device has been rejected by the admin.</strong><br />
            If you believe this was a mistake, please contact the admin for clarification.
          </>
        ) : (
          <>
            🚫 <strong>This device is not yet approved.</strong><br />
            Please provide the following temporary code to the admin for approval:<br />
            <strong>Temporary Code:</strong>{" "}
            <span className="badge bg-secondary">{status.temporary_code}</span><br />
            <small>If you think this is a mistake, please contact the admin.</small>
          </>
        )}
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
