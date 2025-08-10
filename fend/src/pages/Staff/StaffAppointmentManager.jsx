import { useEffect, useState } from "react";
import api from "../../api/api";
import LoadingSpinner from "../../components/LoadingSpinner";

export default function StaffAppointmentManager() {
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState(null); // for rejection modal
  const [note, setNote] = useState("");
  const [processingId, setProcessingId] = useState(null); // holds appointment ID being processed

  useEffect(() => {
    fetchAppointments();
  }, []);

  const fetchAppointments = async () => {
    try {
      const res = await api.get("/api/appointments?status=pending");
      setAppointments(res.data);
    } catch (err) {
      console.error("Failed to load appointments", err);
    } finally {
      setLoading(false);
    }
  };

  const approve = async (id) => {
    setProcessingId(id);
    try {
      await api.post(`/api/appointments/${id}/approve`);
      fetchAppointments();
    } catch (err) {
      console.error("Approve error:", err.response?.data || err.message);
      alert(err.response?.data?.error || "Approval failed");
    } finally {
      setProcessingId(null);
    }
  };

  const reject = async () => {
    if (!note.trim()) return alert("Note is required");

    setProcessingId(selected.id);
    try {
      await api.post(`/api/appointments/${selected.id}/reject`, { note });
      setSelected(null);
      setNote("");
      fetchAppointments();
    } catch (err) {
      console.error("Reject error:", err.response?.data || err.message);
      alert(err.response?.data?.error || "Rejection failed");
    } finally {
      setProcessingId(null);
    }
  };

  if (loading) return <LoadingSpinner />;

  return (
    <div className="p-4">
      <h1 className="text-xl font-bold mb-4">Pending Appointments</h1>
      {appointments.length === 0 ? (
        <p>No pending appointments.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="table-auto w-full border text-sm">
            <thead>
              <tr className="bg-gray-100">
                <th className="p-2 border">#</th>
                <th className="p-2 border">Service</th>
                <th className="p-2 border">Date</th>
                <th className="p-2 border">Time</th>
                <th className="p-2 border">Payment</th>
                <th className="p-2 border">Status</th>
                <th className="p-2 border">Action</th>
              </tr>
            </thead>
            <tbody>
              {appointments.map((appt, i) => (
                <tr key={appt.id} className="text-center">
                  <td className="p-2 border">{i + 1}</td>
                  <td className="p-2 border">{appt.service?.name}</td>
                  <td className="p-2 border">{appt.date}</td>
                  <td className="p-2 border">{appt.time_slot}</td>
                  <td className="p-2 border">{appt.payment_method}</td>
                  <td className="p-2 border capitalize">{appt.status}</td>
                  <td className="p-2 border">
                    <div className="flex justify-center gap-2">
                      <button
                        onClick={() => approve(appt.id)}
                        disabled={processingId === appt.id}
                        className="px-2 py-1 bg-green-600 text-white rounded text-xs disabled:opacity-50"
                      >
                        {processingId === appt.id ? "Approving..." : "Approve"}
                      </button>

                      <button
                        onClick={() => setSelected(appt)}
                        disabled={processingId === appt.id}
                        className="px-2 py-1 bg-red-600 text-white rounded text-xs disabled:opacity-50"
                      >
                        {processingId === appt.id ? "Rejecting..." : "Reject"}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Reject Modal */}
      {selected && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-10">
          <div className="bg-white p-6 rounded w-96 shadow-lg">
            <h2 className="text-lg font-bold mb-2">Reject Appointment</h2>
            <p className="text-sm mb-2">
              Enter reason for rejecting appointment on {selected.date} at{" "}
              {selected.time_slot}
            </p>
            <textarea
              value={note}
              onChange={(e) => setNote(e.target.value)}
              className="w-full border p-2 mb-2"
              rows={3}
            />
            <div className="flex justify-end gap-2">
              <button
                className="px-3 py-1 bg-gray-300 rounded"
                onClick={() => setSelected(null)}
              >
                Cancel
              </button>
              <button
                className="px-3 py-1 bg-red-600 text-white rounded"
                onClick={reject}
              >
                Reject
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
