import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../../api/api";

const PatientProfile = () => {
  const [user, setUser] = useState(null);
  const [showContactForm, setShowContactForm] = useState(false);
  const [contactNumber, setContactNumber] = useState("");
  const [errors, setErrors] = useState({});
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();
  const fetchUser = async () => {
    try {
      const res = await api.get("/api/user");
      setUser(res.data);
      console.log(res.data);
    } catch (err) {
      console.error("Failed to fetch user info", err);
    }
  };

  useEffect(() => {
    fetchUser();
  }, []);

  const handleResetRequest = async () => {
    try {
      await api.post("/logout");
      localStorage.removeItem("token");
    } catch (err) {
      console.warn("Logout failed, proceeding anyway.");
    }

    navigate("/forgot-password", {
      state: { email: user.email, fromProfile: true },
    });
  };

  const handleLinkSelf = async () => {
    setLoading(true);
    setErrors({});
    try {
      await api.post("/api/patients/link-self", {
        contact_number: contactNumber,
      });

      setMessage("✅ Profile linked successfully.");
      await fetchUser(); // 🔁 re-fetch updated user data here
      setShowContactForm(false);
    } catch (err) {
      if (err.response?.data?.errors) {
        setErrors(err.response.data.errors);
      } else {
        setMessage("❌ Failed to link profile.");
      }
    } finally {
      setLoading(false);
    }
  };

  if (!user) return <p>Loading your profile...</p>;
  const isLinked = !!user.patient && user.patient.is_linked;

  return (
    <div className="container mt-4" style={{ maxWidth: "600px" }}>
      <h2 className="mb-4">My Account</h2>

      <div className="mb-4 p-3 border rounded bg-light">
        <p>
          <strong>Name:</strong> {user.name}
        </p>
        <p>
          <strong>Email:</strong> {user.email}
        </p>
      </div>

      <button
        className="btn btn-outline-primary mb-4"
        onClick={handleResetRequest}
      >
        Send Password Reset Link to My Email
      </button>

      {!isLinked && (
        <div className="border rounded p-3 bg-warning-subtle">
          <p>
            <strong>You're not yet linked to a patient profile.</strong>
          </p>
          <p>Have you visited the clinic before?</p>

          <div className="mb-3">
            <div className="alert alert-secondary d-inline-block me-3 p-2">
              ✅ Yes – Please visit the clinic for assistance
            </div>
            <button
              className="btn btn-outline-primary"
              onClick={() => {
                if (user?.contact_number) setContactNumber(user.contact_number);
                setShowContactForm(true);
              }}
            >
              ❌ No – I haven’t visited before
            </button>
          </div>

          {showContactForm && (
            <div className="mt-3">
              <label className="form-label">Contact Number</label>
              <input
                className="form-control"
                placeholder="e.g. 09123456789"
                value={contactNumber}
                onChange={(e) => setContactNumber(e.target.value)}
              />
              {errors.contact_number && (
                <div className="text-danger mt-1">
                  {errors.contact_number[0]}
                </div>
              )}
              <button
                className="btn btn-success mt-2"
                onClick={handleLinkSelf}
                disabled={loading}
              >
                {loading ? "Linking..." : "Submit"}
              </button>
            </div>
          )}

          {message && <div className="mt-3 fw-bold">{message}</div>}
        </div>
      )}
    </div>
  );
};

export default PatientProfile;
