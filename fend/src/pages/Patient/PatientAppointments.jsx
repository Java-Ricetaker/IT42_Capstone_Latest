import { useEffect, useState } from "react";
import api from "../../api/api";
import LoadingSpinner from "../../components/LoadingSpinner";

function PatientAppointments() {
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [meta, setMeta] = useState({});
  const [paying, setPaying] = useState(null); // appointment_id being processed

  useEffect(() => {
    fetchAppointments(currentPage);
  }, [currentPage]);

  const fetchAppointments = async (page = 1) => {
    try {
      const res = await api.get(`/api/user-appointments?page=${page}`);
      // Expect each item to include: payment_method, payment_status, status, service{name}, notes, date
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
    if (!window.confirm("Are you sure you want to cancel this appointment?")) return;
    try {
      await api.post(`/api/appointment/${id}/cancel`);
      alert("Appointment canceled.");
      fetchAppointments(currentPage);
    } catch (err) {
      console.error("Cancel failed", err);
      alert("Failed to cancel appointment.");
    }
  };

  const handlePayNow = async (appointmentId) => {
    try {
      setPaying(appointmentId);
      // Backend computes amount and creates Maya one-time payment, returns { redirect_url }
      const { data } = await api.post("/api/maya/payments", { appointment_id: appointmentId });
      if (data?.redirect_url) {
        window.location.href = data.redirect_url; // go to Maya payment page
      } else {
        alert("Payment link not available. Please try again.");
      }
    } catch (err) {
      console.error("Create Maya payment failed", err);
      alert("Unable to start payment. Please try again.");
    } finally {
      setPaying(null);
    }
  };

  const renderStatusBadge = (status) => {
    const map = {
      approved: "bg-success",
      pending: "bg-warning text-dark",
      rejected: "bg-danger",
      cancelled: "bg-secondary text-white fw-semibold",
      completed: "bg-primary",
    };
    return <span className={`badge ${map[status] || "bg-secondary"}`}>{status}</span>;
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
                <th>Payment Method</th>
                <th>Payment Status</th>
                <th>Appt. Status</th>
                <th>Note</th>
                <th style={{ width: 160 }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {appointments.map((a) => {
                const showPayNow =
                  a.payment_method === "maya" &&
                  a.payment_status === "awaiting_payment";
                  // optionally also require approval:
                  // && a.status === "approved";

                return (
                  <tr key={a.id}>
                    <td>{a.date}</td>
                    <td>{a.service?.name || "—"}</td>
                    <td className="text-capitalize">{a.payment_method}</td>
                    <td>
                      <span className={`badge ${a.payment_status === "paid"
                          ? "bg-success"
                          : a.payment_status === "awaiting_payment"
                          ? "bg-warning text-dark"
                          : "bg-secondary"
                        }`}>
                        {a.payment_status}
                      </span>
                    </td>
                    <td>{renderStatusBadge(a.status)}</td>
                    <td className="text-muted small">{a.notes || "—"}</td>
                    <td>
                      <div className="d-flex gap-1 flex-wrap">
                        {showPayNow && (
                          <button
                            className="btn btn-primary btn-sm"
                            onClick={() => handlePayNow(a.id)}
                            disabled={paying === a.id}
                          >
                            {paying === a.id ? "Redirecting..." : "Pay now"}
                          </button>
                        )}

                        {a.status !== "cancelled" && a.status !== "rejected" && (
                          <button
                            className="btn btn-outline-danger btn-sm"
                            onClick={() => handleCancel(a.id)}
                          >
                            Cancel
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })}
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
