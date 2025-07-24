import { useEffect, useState } from "react";
import api from "../../api/api";

const weekdayLabels = [
  "Sunday",
  "Monday",
  "Tuesday",
  "Wednesday",
  "Thursday",
  "Friday",
  "Saturday",
];

function WeeklyScheduleManager() {
  const [schedules, setSchedules] = useState([]);
  const [savingId, setSavingId] = useState(null); // track which row is saving

  useEffect(() => {
    fetchSchedules();
  }, []);

  const fetchSchedules = async () => {
    try {
      const res = await api.get("/api/weekly-schedule");
      setSchedules(res.data);
    } catch (err) {
      console.error("Failed to load weekly schedule", err);
    }
  };

  const handleChange = (id, field, value) => {
    setSchedules((prev) =>
      prev.map((row) => {
        if (row.id !== id) return row;

        if (field === "is_open") {
          const newIsOpen = value;
          return {
            ...row,
            is_open: newIsOpen,
            // If closed, clear max_per_slot
            max_per_slot: newIsOpen ? row.max_per_slot || 1 : null,
          };
        }

        return {
          ...row,
          [field]: value,
        };
      })
    );
  };

  const handleSave = async (id) => {
    const schedule = schedules.find((row) => row.id === id);
    setSavingId(id);
    try {
      await api.patch(`/api/weekly-schedule/${id}`, {
        is_open: schedule.is_open,
        open_time: schedule.open_time,
        close_time: schedule.close_time,
        dentist_count: Number(schedule.dentist_count),
        max_per_slot: Number(schedule.max_per_slot),
        note: schedule.note,
      });
      alert(`✅ ${weekdayLabels[schedule.weekday]} saved.`);
    } catch (err) {
      console.error("Failed to save", err);
      alert("❌ Save failed. See console.");
    } finally {
      setSavingId(null);
    }
  };

  const autoResize = (e) => {
    e.target.style.height = "auto";
    e.target.style.height = e.target.scrollHeight + "px";
  };

  return (
    <div>
      <h5 className="mb-3">🗓️ Weekly Default Schedule (Fallback)</h5>
      <table className="table table-bordered">
        <thead>
          <tr>
            <th>Day</th>
            <th>Open?</th>
            <th>Opening</th>
            <th>Closing</th>
            <th>Dentists</th>
            <th>Max/Slot</th>
            <th>Note</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          {schedules.map((s) => (
            <tr key={s.id}>
              <td>{weekdayLabels[s.weekday]}</td>
              <td>
                <select
                  className="form-select"
                  style={{ minWidth: "80px" }}
                  value={s.is_open ? "true" : "false"}
                  onChange={(e) =>
                    handleChange(s.id, "is_open", e.target.value === "true")
                  }
                >
                  <option value="true">Yes</option>
                  <option value="false">No</option>
                </select>
              </td>
              <td>
                {!s.is_open ? (
                  <input className="form-control" disabled placeholder="--" />
                ) : (
                  <input
                    type="time"
                    className="form-control"
                    value={s.open_time || ""}
                    onChange={(e) =>
                      handleChange(s.id, "open_time", e.target.value)
                    }
                  />
                )}
              </td>
              <td>
                {!s.is_open ? (
                  <input className="form-control" disabled placeholder="--" />
                ) : (
                  <input
                    type="time"
                    className="form-control"
                    value={s.close_time || ""}
                    onChange={(e) =>
                      handleChange(s.id, "close_time", e.target.value)
                    }
                  />
                )}
              </td>
              <td>
                {!s.is_open ? (
                  <input className="form-control" disabled placeholder="--" />
                ) : (
                  <input
                    type="number"
                    className="form-control"
                    min="0"
                    max="20"
                    value={s.dentist_count}
                    onChange={(e) =>
                      handleChange(s.id, "dentist_count", e.target.value)
                    }
                  />
                )}
              </td>
              <td>
                {!s.is_open ? (
                  <input className="form-control" disabled placeholder="--" />
                ) : (
                  <>
                    <input
                      type="number"
                      className="form-control"
                      min="1"
                      max="20"
                      value={s.max_per_slot || ""}
                      onChange={(e) =>
                        handleChange(s.id, "max_per_slot", e.target.value)
                      }
                    />
                    {parseInt(s.max_per_slot) > parseInt(s.dentist_count) && (
                      <small className="text-danger">
                        ⚠ Exceeds dentist count
                      </small>
                    )}
                  </>
                )}
              </td>
              <td>
                <input
                  type="text"
                  className="form-control"
                  value={s.note || ""}
                  onChange={(e) => handleChange(s.id, "note", e.target.value)}
                />
              </td>
              <td>
                <button
                  className="btn btn-sm btn-primary"
                  onClick={() => handleSave(s.id)}
                  disabled={savingId === s.id}
                >
                  {savingId === s.id ? "Saving..." : "Save"}
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default WeeklyScheduleManager;
