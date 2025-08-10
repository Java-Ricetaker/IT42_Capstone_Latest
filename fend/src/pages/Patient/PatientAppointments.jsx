import { useEffect, useState } from "react";
import api from "../../api/api";
import LoadingSpinner from "../../components/LoadingSpinner";

function PatientAppointments() {
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [meta, setMeta] = useState({}); // for pagination metadata

  useEffect(() => {
    fetchAppointments(currentPage); // Use state for page
  }, [currentPage]);

  const fetchAppointments = async (page = 1) => {
    try {
      const res = await api.get(`/api/user-appointments?page=${page}`);
      setAppointments(res.data.data);
      setMeta({
        current_page: res.data.current_page,
        last_page: res.data.last_page,
        per_page: res.data.per_page,
        total: res.data.total,
      });
    } catch (err) {
      console.error("Failed to fetch appointments", err);
    } finally {
      setLoading(false);
    }
  };

  const handleCancel = async (id) => {
    if (!window.confirm("Are you sure you want to cancel this appointment?"))
      return;

    try {
      await api.post(`/api/appointment/${id}/cancel`);
      alert("Appointment canceled.");
      fetchAppointments(currentPage); // Refresh
    } catch (err) {
      console.error("Cancel failed", err);
      alert("Failed to cancel appointment.");
    }
  };

  return (
    <div className="container mt-4">
      <h3>📋 My Appointments</h3>

      {loading && <LoadingSpinner message="Loading appointments..." />}

      {!loading && appointments.length === 0 && (
        <p className="text-muted mt-3">You have no appointments yet.</p>
      )}

      {!loading && appointments.length > 0 && (
        <div className="table-responsive mt-3">
          <table className="table table-bordered table-sm align-middle">
            <thead className="table-light">
              <tr>
                <th>Date</th>
                <th>Service</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              {appointments.map((a) => (
                <tr key={a.id}>
                  <td>{a.date}</td>
                  <td>{a.service?.name || "—"}</td>
                  <td>{a.payment_method}</td>
                  <td>
                    <span
                      className={`badge ${
                        a.status === "approved"
                          ? "bg-success"
                          : a.status === "pending"
                          ? "bg-warning text-dark"
                          : a.status === "rejected"
                          ? "bg-danger"
                          : a.status === "cancelled"
                          ? "bg-secondary text-white fw-semibold"
                          : "bg-secondary"
                      }`}
                    >
                      {a.status}
                    </span>
                  </td>
                  <td className="text-muted small">{a.notes || "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <div className="d-flex justify-content-between align-items-center mt-3">
            <button
              className="btn btn-outline-secondary btn-sm"
              disabled={currentPage === 1}
              onClick={() => setCurrentPage(currentPage - 1)}
            >
              ◀ Previous
            </button>

            <span>
              Page {meta.current_page} of {meta.last_page}
            </span>

            <button
              className="btn btn-outline-secondary btn-sm"
              disabled={currentPage === meta.last_page}
              onClick={() => setCurrentPage(currentPage + 1)}
            >
              Next ▶
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

export default PatientAppointments;
