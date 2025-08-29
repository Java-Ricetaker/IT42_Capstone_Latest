import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import useNotifications from "../context/NotificationsContext";

export default function NotificationBell() {
  const { items, unread, loading, error, loadList, loadUnread, markAllRead } = useNotifications();
  const [open, setOpen] = useState(false);

  useEffect(() => { loadUnread(); }, [loadUnread]);

  const toggle = async () => {
    const next = !open;
    setOpen(next);
    if (next) {
      await loadList();      // fetch list
      await markAllRead();   // clear badge (Option 1)
      await loadUnread();    // should return 0
    }
  };

  return (
    <div className="position-relative">
      <button
        onClick={toggle}
        className="btn btn-light d-inline-flex align-items-center"
        title="Notifications"
        aria-label="Notifications"
      >
        <span role="img" aria-label="bell">🔔</span>
        {unread > 0 && <span className="badge bg-danger ms-2">{unread > 99 ? "99+" : unread}</span>}
      </button>

      {open && (
        <div className="position-absolute end-0 mt-2" style={{ width: 340, zIndex: 1050 }}>
          <div className="card shadow">
            <div className="card-header py-2">
              <strong>Notifications</strong>
              <div className="small text-muted">Clinic updates & alerts</div>
            </div>

            <div className="list-group list-group-flush" style={{ maxHeight: 340, overflow: "auto" }}>
              {loading && <div className="list-group-item small text-muted">Loading…</div>}
              {error && !loading && <div className="list-group-item small text-danger">{error}</div>}
              {!loading && !error && items.length === 0 && (
                <div className="list-group-item small text-muted">No notifications.</div>
              )}
              {!loading && !error && items.map((n) => (
                <div key={n.id} className="list-group-item small">
                  <div className="d-flex justify-content-between align-items-start">
                    <div className="me-2">
                      <div className="fw-semibold">
                        {n.title || "Notification"}
                        {n.severity === "danger"  && <span className="badge bg-danger ms-2">Important</span>}
                        {n.severity === "warning" && <span className="badge bg-warning text-dark ms-2">Warning</span>}
                        {n.severity === "info"    && <span className="badge bg-info text-dark ms-2">Info</span>}
                      </div>
                      {n.body && <div className="text-muted mt-1">{n.body}</div>}
                      {n.data?.date && <div className="text-muted">Date: {n.data.date}</div>}
                    </div>
                    <small className="text-muted">{new Date(n.created_at).toLocaleString()}</small>
                  </div>
                </div>
              ))}
            </div>

            <div className="card-footer py-2 d-flex justify-content-between">
              <Link to="/notifications" className="btn btn-link btn-sm p-0">See all</Link>
              <button className="btn btn-link btn-sm p-0" onClick={() => setOpen(false)}>Close</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
