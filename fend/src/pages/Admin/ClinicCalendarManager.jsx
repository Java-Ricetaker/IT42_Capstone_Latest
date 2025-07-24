import { useEffect, useState } from "react";
import api from "../../api/api";

function ClinicCalendarManager() {
  const [entries, setEntries] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showAddModal, setShowAddModal] = useState(false);
  const [newDate, setNewDate] = useState("");
  const [newIsOpen, setNewIsOpen] = useState(true);
  const [newDentistCount, setNewDentistCount] = useState(1);
  const [newNote, setNewNote] = useState("");
  const [showEditModal, setShowEditModal] = useState(false);
  const [editEntry, setEditEntry] = useState(null);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deleteEntry, setDeleteEntry] = useState(null);
  const [existingOverride, setExistingOverride] = useState(null);
  const [newOpenTime, setNewOpenTime] = useState("");
  const [newCloseTime, setNewCloseTime] = useState("");

  useEffect(() => {
    fetchEntries();
  }, []);

  const fetchEntries = async () => {
    try {
      const res = await api.get("/api/clinic-calendar");
      setEntries(res.data);
      setLoading(false);
    } catch (err) {
      console.error("Failed to fetch clinic calendar", err);
      setLoading(false);
    }
  };

  const handleAdd = async (e) => {
    e.preventDefault();
    try {
      const payload = {
        date: newDate,
        is_open: newIsOpen,
        open_time: formatTo24Hour(newOpenTime),
        close_time: formatTo24Hour(newCloseTime),
        dentist_count: Number(newDentistCount),
        note: newNote,
      };

      if (existingOverride) {
        await api.put(`/api/clinic-calendar/${existingOverride.id}`, payload);
      } else {
        console.log("Submitting payload:", payload);
        await api.post("/api/clinic-calendar", payload);
      }

      setShowAddModal(false);
      setNewDate("");
      setNewIsOpen(true);
      setNewDentistCount(1);
      setNewNote("");
      setNewOpenTime("");
      setNewCloseTime("");
      setExistingOverride(null);

      fetchEntries(); // refresh table
    } catch (err) {
      console.error("Failed to add entry", err);
      alert("Failed to add entry. Maybe the date already exists?");
    }
  };

  useEffect(() => {
    const fetchResolvedSchedule = async () => {
      if (!newDate) return;

      try {
        const res = await api.get("/api/clinic-calendar/resolve", {
          params: { date: newDate },
        });

        const { source, data } = res.data;

        if (source === "override") {
          setExistingOverride(data);
          setNewIsOpen(data.is_open);
          setNewDentistCount(data.dentist_count);
          setNewOpenTime(data.open_time ?? "");
          setNewCloseTime(data.close_time ?? "");
          setNewNote(data.note ?? "");
          alert("⚠️ This date already has an override. You are editing it.");
        } else {
          setExistingOverride(null);
          setNewIsOpen(data.is_open);
          setNewDentistCount(data.dentist_count);
          setNewOpenTime(data.open_time ?? "");
          setNewCloseTime(data.close_time ?? "");
          setNewNote(data.note ?? "");
        }
      } catch (err) {
        console.error("Failed to resolve schedule", err);
      }
    };

    fetchResolvedSchedule();
  }, [newDate]);

  const openEditModal = (entry) => {
    setEditEntry({
      ...entry,
      open_time: entry.open_time ?? "",
      close_time: entry.close_time ?? "",
    });
    setShowEditModal(true);
  };

  const handleEdit = async (e) => {
    e.preventDefault();
    try {
      await api.put(`/api/clinic-calendar/${editEntry.id}`, {
        is_open: editEntry.is_open,
        open_time: editEntry.open_time,
        close_time: editEntry.close_time,
        dentist_count: Number(editEntry.dentist_count),
        note: editEntry.note,
      });
      setShowEditModal(false);
      setEditEntry(null);
      fetchEntries();
    } catch (err) {
      console.error("Failed to update entry", err);
      alert("Update failed.");
    }
  };

  const openDeleteModal = (entry) => {
    setDeleteEntry(entry);
    setShowDeleteModal(true);
  };

  const handleDelete = async () => {
    try {
      await api.delete(`/api/clinic-calendar/${deleteEntry.id}`);
      setShowDeleteModal(false);
      setDeleteEntry(null);
      fetchEntries();
    } catch (err) {
      console.error("Failed to delete entry", err);
      alert("Deletion failed.");
    }
  };

  const formatTo24Hour = (time) => {
    const [h, m] = time.split(":");
    const isPM = time.toLowerCase().includes("pm");
    let hour = parseInt(h);
    if (isPM && hour < 12) hour += 12;
    if (!isPM && hour === 12) hour = 0;
    return `${hour.toString().padStart(2, "0")}:${m.slice(0, 2)}`;
  };

  return (
    <div>
      <div className="d-flex justify-content-between align-items-center mb-3">
        <h2>📅 Clinic Calendar Manager</h2>
        <button
          className="btn btn-primary"
          onClick={() => setShowAddModal(true)}
        >
          ➕ Add Entry
        </button>
      </div>

      {loading ? (
        <p>Loading...</p>
      ) : (
        <table className="table table-bordered">
          <thead>
            <tr>
              <th>Date</th>
              <th>Open?</th>
              <th>Dentists</th>
              <th>Opening</th>
              <th>Closing</th>
              <th>Note</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {entries.length === 0 ? (
              <tr>
                <td colSpan="5">No entries yet.</td>
              </tr>
            ) : (
              entries.map((entry) => (
                <tr key={entry.id}>
                  <td>{new Date(entry.date).toLocaleDateString()}</td>
                  <td>{entry.is_open ? "✅ Yes" : "❌ No"}</td>
                  <td>{entry.dentist_count}</td>
                  <td>{entry.open_time?.slice(0, 5) || "-"}</td>
                  <td>{entry.close_time?.slice(0, 5) || "-"}</td>
                  <td>{entry.note || "-"}</td>
                  <td>
                    <button
                      className="btn btn-sm btn-warning me-2"
                      onClick={() => openEditModal(entry)}
                    >
                      Edit
                    </button>
                    <button
                      className="btn btn-sm btn-danger"
                      onClick={() => openDeleteModal(entry)}
                    >
                      Delete
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      )}

      {/* Add Modal */}
      {showAddModal && (
        <div
          className="modal fade show d-block"
          tabIndex="-1"
          style={{ background: "rgba(0,0,0,0.5)" }}
        >
          <div className="modal-dialog">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Add Clinic Calendar Entry</h5>
                <button
                  type="button"
                  className="btn-close"
                  onClick={() => setShowAddModal(false)}
                ></button>
              </div>
              <div className="modal-body">
                <form onSubmit={handleAdd}>
                  <div className="mb-3">
                    <label className="form-label">Date</label>
                    <input
                      type="date"
                      className="form-control"
                      required
                      value={newDate}
                      onChange={(e) => setNewDate(e.target.value)}
                      disabled={!!existingOverride}
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Is Open?</label>
                    <select
                      className="form-select"
                      value={newIsOpen}
                      onChange={(e) => setNewIsOpen(e.target.value === "true")}
                    >
                      <option value="true">Yes</option>
                      <option value="false">No</option>
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Dentist Count</label>
                    <input
                      type="number"
                      className="form-control"
                      min="0"
                      max="20"
                      value={newDentistCount}
                      onChange={(e) => setNewDentistCount(e.target.value)}
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Opening Time</label>
                    <input
                      type="time"
                      className="form-control"
                      value={newOpenTime}
                      onChange={(e) => setNewOpenTime(e.target.value)}
                      required={newIsOpen}
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Closing Time</label>
                    <input
                      type="time"
                      className="form-control"
                      value={newCloseTime}
                      onChange={(e) => setNewCloseTime(e.target.value)}
                      required={newIsOpen}
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Note</label>
                    <input
                      type="text"
                      className="form-control"
                      value={newNote}
                      onChange={(e) => setNewNote(e.target.value)}
                    />
                  </div>
                  <div className="d-flex justify-content-end">
                    <button
                      type="button"
                      className="btn btn-secondary me-2"
                      onClick={() => {
                        setShowAddModal(false);
                        setNewDate("");
                        setNewIsOpen(true);
                        setNewDentistCount(1);
                        setNewOpenTime("");
                        setNewCloseTime("");
                        setNewNote("");
                        setExistingOverride(null);
                      }}
                    >
                      Cancel
                    </button>
                    <button type="submit" className="btn btn-primary">
                      Save
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      )}
      {/* Edit Modal */}
      {showEditModal && editEntry && (
        <div
          className="modal fade show d-block"
          tabIndex="-1"
          style={{ background: "rgba(0,0,0,0.5)" }}
        >
          <div className="modal-dialog">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Edit Clinic Calendar Entry</h5>
                <button
                  type="button"
                  className="btn-close"
                  onClick={() => setShowEditModal(false)}
                ></button>
              </div>
              <div className="modal-body">
                <form onSubmit={handleEdit}>
                  <div className="mb-3">
                    <label className="form-label">Date</label>
                    <input
                      type="date"
                      className="form-control"
                      value={editEntry.date.slice(0, 10)}
                      disabled
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Is Open?</label>
                    <select
                      className="form-select"
                      value={editEntry.is_open ? "true" : "false"}
                      onChange={(e) =>
                        setEditEntry({
                          ...editEntry,
                          is_open: e.target.value === "true",
                        })
                      }
                    >
                      <option value="true">Yes</option>
                      <option value="false">No</option>
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Dentist Count</label>
                    <input
                      type="number"
                      className="form-control"
                      min="0"
                      max="20"
                      value={editEntry.dentist_count}
                      onChange={(e) =>
                        setEditEntry({
                          ...editEntry,
                          dentist_count: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Opening Time</label>
                    <input
                      type="time"
                      className="form-control"
                      value={editEntry.open_time}
                      onChange={(e) =>
                        setEditEntry({
                          ...editEntry,
                          open_time: e.target.value,
                        })
                      }
                    />
                  </div>

                  <div className="mb-3">
                    <label className="form-label">Closing Time</label>
                    <input
                      type="time"
                      className="form-control"
                      value={editEntry.close_time}
                      onChange={(e) =>
                        setEditEntry({
                          ...editEntry,
                          close_time: e.target.value,
                        })
                      }
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Note</label>
                    <input
                      type="text"
                      className="form-control"
                      value={editEntry.note || ""}
                      onChange={(e) =>
                        setEditEntry({ ...editEntry, note: e.target.value })
                      }
                    />
                  </div>
                  <div className="d-flex justify-content-end">
                    <button
                      type="button"
                      className="btn btn-secondary me-2"
                      onClick={() => setShowEditModal(false)}
                    >
                      Cancel
                    </button>
                    <button type="submit" className="btn btn-primary">
                      Update
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      )}
      {/* Delete Modal */}
      {showDeleteModal && deleteEntry && (
        <div
          className="modal fade show d-block"
          tabIndex="-1"
          style={{ background: "rgba(0,0,0,0.5)" }}
        >
          <div className="modal-dialog">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title text-danger">Confirm Deletion</h5>
                <button
                  type="button"
                  className="btn-close"
                  onClick={() => setShowDeleteModal(false)}
                ></button>
              </div>
              <div className="modal-body">
                <p>
                  Are you sure you want to delete the calendar entry for{" "}
                  <strong>{deleteEntry.date.slice(0, 10)}</strong>?
                </p>
                <p>
                  This action <strong>cannot be undone</strong>.
                </p>
                <div className="d-flex justify-content-end">
                  <button
                    className="btn btn-secondary me-2"
                    onClick={() => setShowDeleteModal(false)}
                  >
                    Cancel
                  </button>
                  <button className="btn btn-danger" onClick={handleDelete}>
                    Delete
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export default ClinicCalendarManager;
