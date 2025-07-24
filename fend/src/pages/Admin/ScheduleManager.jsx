import { useState } from "react";
import ClinicCalendarManager from "./ClinicCalendarManager";
import WeeklyScheduleManager from "./WeeklyScheduleManager";

function ScheduleManager() {
  const [activeTab, setActiveTab] = useState("calendar");

  return (
    <div className="container mt-4">
      <h2>🦷 Clinic Schedule Management</h2>

      <div className="btn-group my-3">
        <button
          className={`btn btn-${activeTab === "calendar" ? "primary" : "outline-primary"}`}
          onClick={() => setActiveTab("calendar")}
        >
          📅 Calendar Overrides
        </button>
        <button
          className={`btn btn-${activeTab === "weekly" ? "primary" : "outline-primary"}`}
          onClick={() => setActiveTab("weekly")}
        >
          🔁 Weekly Defaults
        </button>
      </div>

      <div>
        {activeTab === "calendar" ? <ClinicCalendarManager /> : <WeeklyScheduleManager />}
      </div>
    </div>
  );
}

export default ScheduleManager;
