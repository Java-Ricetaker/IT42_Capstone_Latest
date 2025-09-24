import { useEffect, useMemo, useState } from "react";
import api from "../../api/api";

// Minimal, dependency-free charts using inline SVG to avoid adding new libs
function LineChart({ data, width = 640, height = 220, labelKey = "day", valueKey = "count" }) {
  const padding = 32;
  const points = useMemo(() => {
    if (!Array.isArray(data) || data.length === 0) return [];
    const xs = data.map((_, i) => i);
    const ys = data.map((d) => Number(d[valueKey]) || 0);
    const maxY = Math.max(1, ...ys);
    const innerW = width - padding * 2;
    const innerH = height - padding * 2;
    return ys.map((y, i) => {
      const x = padding + (i / Math.max(1, data.length - 1)) * innerW;
      const yy = padding + innerH - (y / maxY) * innerH;
      return [x, yy];
    });
  }, [data, height, padding, valueKey, width]);

  const path = points
    .map((p, i) => (i === 0 ? `M ${p[0]},${p[1]}` : `L ${p[0]},${p[1]}`))
    .join(" ");

  return (
    <svg width={width} height={height} className="w-100 border rounded bg-white">
      <polyline fill="none" stroke="#0d6efd" strokeWidth="2" points={points.map((p) => p.join(",")).join(" ")} />
      <path d={path} fill="none" stroke="#0d6efd" strokeWidth="2" />
    </svg>
  );
}

function BarChart({ data, width = 640, height = 220, labelKey = "label", valueKey = "count" }) {
  const padding = 32;
  const innerW = width - padding * 2;
  const innerH = height - padding * 2;
  const values = data.map((d) => Number(d[valueKey]) || 0);
  const maxY = Math.max(1, ...values);
  const barW = data.length ? innerW / data.length - 6 : 0;
  return (
    <svg width={width} height={height} className="w-100 border rounded bg-white">
      {data.map((d, i) => {
        const v = Number(d[valueKey]) || 0;
        const h = (v / maxY) * innerH;
        const x = padding + i * (barW + 6);
        const y = padding + (innerH - h);
        return <rect key={i} x={x} y={y} width={barW} height={h} fill="#198754" />;
      })}
    </svg>
  );
}

function PieChart({ data, valueKey = "count", colors = ["#0d6efd", "#6c757d", "#198754", "#dc3545", "#ffc107"] }) {
  const total = data.reduce((s, d) => s + (Number(d[valueKey]) || 0), 0) || 1;
  let angle = 0;
  const radius = 90;
  const cx = 110;
  const cy = 110;
  return (
    <svg width={220} height={220} className="border rounded bg-white">
      {data.map((d, i) => {
        const val = Number(d[valueKey]) || 0;
        const a = (val / total) * Math.PI * 2;
        const x1 = cx + radius * Math.cos(angle);
        const y1 = cy + radius * Math.sin(angle);
        const x2 = cx + radius * Math.cos(angle + a);
        const y2 = cy + radius * Math.sin(angle + a);
        const large = a > Math.PI ? 1 : 0;
        const path = `M ${cx} ${cy} L ${x1} ${y1} A ${radius} ${radius} 0 ${large} 1 ${x2} ${y2} Z`;
        angle += a;
        return <path key={i} d={path} fill={colors[i % colors.length]} />;
      })}
    </svg>
  );
}

export default function AdminMonthlyReport() {
  const [month, setMonth] = useState(() => new Date().toISOString().slice(0, 7));
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [data, setData] = useState({ totals: { visits: 0 }, by_day: [], by_hour: [], by_visit_type: [], by_service: [] });

  const load = async () => {
    setLoading(true);
    setError("");
    try {
      const res = await api.get("/api/reports/visits-monthly", { params: { month } });
      setData(res.data || {});
    } catch (e) {
      console.error(e);
      setError(e?.response?.data?.message || "Failed to load report.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [month]);

  const byHour = useMemo(() => {
    const map = new Map();
    for (let i = 0; i < 24; i++) map.set(i, 0);
    (data.by_hour || []).forEach((r) => {
      const h = Number(r.hour) || 0;
      map.set(h, (map.get(h) || 0) + (Number(r.count) || 0));
    });
    return Array.from(map.entries()).map(([h, count]) => ({ label: String(h).padStart(2, "0"), count }));
  }, [data.by_hour]);

  const byDay = useMemo(() => {
    const arr = (data.by_day || []).map((d) => ({ day: d.day, count: Number(d.count) || 0 }));
    return arr;
  }, [data.by_day]);

  const visitType = useMemo(() => {
    const vt = (data.by_visit_type || []).map((r) => ({ label: r.visit_type, count: Number(r.count) || 0 }));
    if (vt.length === 0) return [{ label: "walkin", count: 0 }, { label: "appointment", count: 0 }];
    return vt;
  }, [data.by_visit_type]);

  const byService = useMemo(() => {
    return (data.by_service || []).map((r) => ({ label: r.service_name || "(Unspecified)", count: Number(r.count) || 0 }));
  }, [data.by_service]);

  const downloadPdf = async () => {
    try {
      const { default: jsPDF } = await import("jspdf");
      const { default: autoTable } = await import("jspdf-autotable");
      const doc = new jsPDF({ orientation: "p", unit: "pt", format: "a4" });
      doc.setFontSize(14);
      doc.text(`Monthly Visits Report — ${month}`, 40, 40);
      doc.setFontSize(11);

      autoTable(doc, {
        startY: 60,
        head: [["Metric", "Value"]],
        body: [["Total Visits", String(data?.totals?.visits ?? 0)]],
        theme: "striped",
      });

      autoTable(doc, {
        startY: (doc.lastAutoTable?.finalY || 100) + 20,
        head: [["Day", "Count"]],
        body: (byDay || []).map((r) => [r.day, String(r.count)]),
        theme: "grid",
        styles: { fontSize: 9 },
        headStyles: { fillColor: [13, 110, 253] },
      });

      autoTable(doc, {
        startY: (doc.lastAutoTable?.finalY || 100) + 20,
        head: [["Hour", "Count"]],
        body: (byHour || []).map((r) => [r.label, String(r.count)]),
        theme: "grid",
        styles: { fontSize: 9 },
        headStyles: { fillColor: [25, 135, 84] },
      });

      autoTable(doc, {
        startY: (doc.lastAutoTable?.finalY || 100) + 20,
        head: [["Visit Type", "Count"]],
        body: (visitType || []).map((r) => [r.label, String(r.count)]),
        theme: "grid",
        styles: { fontSize: 9 },
        headStyles: { fillColor: [108, 117, 125] },
      });

      autoTable(doc, {
        startY: (doc.lastAutoTable?.finalY || 100) + 20,
        head: [["Service", "Count"]],
        body: (byService || []).map((r) => [r.label, String(r.count)]),
        theme: "grid",
        styles: { fontSize: 9 },
        headStyles: { fillColor: [220, 53, 69] },
      });

      doc.save(`visits-report-${month}.pdf`);
    } catch (e) {
      console.error(e);
      alert("Failed to generate PDF.");
    }
  };

  return (
    <div className="p-2">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <h3 className="m-0">📈 Monthly Visits Report</h3>
        <div className="d-flex gap-2 align-items-center">
          <input
            type="month"
            className="form-control"
            style={{ width: 170 }}
            value={month}
            onChange={(e) => setMonth(e.target.value)}
            aria-label="Select month"
          />
          <button className="btn btn-outline-secondary" onClick={load} disabled={loading}>
            Refresh
          </button>
          <button className="btn btn-dark" onClick={downloadPdf} disabled={loading}>
            Download PDF
          </button>
        </div>
      </div>

      {error && (
        <div className="alert alert-danger py-2" role="alert">
          {error}
        </div>
      )}

      {loading ? (
        <div>Loading…</div>
      ) : (
        <>
          <div className="row g-3 mb-3">
            <div className="col-12 col-md-3">
              <div className="card h-100">
                <div className="card-body">
                  <div className="text-muted">Total Visits</div>
                  <div className="fs-3 fw-bold">{data?.totals?.visits ?? 0}</div>
                </div>
              </div>
            </div>
          </div>

          <div className="row g-3">
            <div className="col-12">
              <div className="card">
                <div className="card-header">Daily Counts</div>
                <div className="card-body">
                  <LineChart data={byDay} />
                </div>
              </div>
            </div>
            <div className="col-12 col-lg-6">
              <div className="card">
                <div className="card-header">By Hour</div>
                <div className="card-body">
                  <BarChart data={byHour} />
                </div>
              </div>
            </div>
            <div className="col-12 col-lg-6">
              <div className="card">
                <div className="card-header">Visit Type</div>
                <div className="card-body d-flex align-items-center justify-content-center">
                  <PieChart data={visitType} />
                  <div className="ms-3">
                    {visitType.map((v) => (
                      <div key={v.label} className="d-flex align-items-center mb-1">
                        <span className="badge text-bg-secondary me-2" style={{ width: 16 }}>&nbsp;</span>
                        <span className="me-2" style={{ minWidth: 90 }}>{v.label}</span>
                        <strong>{v.count}</strong>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </div>
            <div className="col-12">
              <div className="card">
                <div className="card-header">By Service</div>
                <div className="card-body">
                  <BarChart data={byService} />
                </div>
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  );
}

