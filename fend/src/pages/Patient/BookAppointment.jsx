import { useNavigate } from "react-router-dom";
import { useState, useEffect } from "react";
import api from "../../api/api";
import LoadingSpinner from "../../components/LoadingSpinner";

function BookAppointment() {
  const navigate = useNavigate();

  const [selectedDate, setSelectedDate] = useState("");
  const [services, setServices] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const [selectedService, setSelectedService] = useState(null);
  const [availableSlots, setAvailableSlots] = useState([]);
  const [selectedSlot, setSelectedSlot] = useState("");
  const [paymentMethod, setPaymentMethod] = useState("cash");
  const [bookingMessage, setBookingMessage] = useState("");

  const fetchServices = async (date) => {
    setLoading(true);
    setError("");
    try {
      const res = await api.get(
        `/api/appointment/available-services?date=${date}`
      );
      setServices(res.data);
    } catch (err) {
      setServices([]);
      setError(err.response?.data?.message || "Something went wrong.");
    } finally {
      setLoading(false);
    }
  };

  const fetchSlots = async (serviceId) => {
    setAvailableSlots([]);
    try {
      const res = await api.get(
        `/api/appointment/available-slots?date=${selectedDate}&service_id=${serviceId}`
      );
      setAvailableSlots(res.data.slots);
    } catch {
      setAvailableSlots([]);
    }
  };

  const handleDateChange = (e) => {
    const date = e.target.value;
    setSelectedDate(date);
    setServices([]);
    setSelectedService(null);
    setAvailableSlots([]);
    setSelectedSlot("");
    setPaymentMethod("cash");
    setBookingMessage("");
    if (date) fetchServices(date);
  };

  const handleServiceSelect = (service) => {
    setSelectedService(service);
    fetchSlots(service.id);
    setSelectedSlot("");
    setBookingMessage("");
  };

  const handleBookingSubmit = async () => {
    if (!selectedDate || !selectedService || !selectedSlot || !paymentMethod) {
      setBookingMessage("Please complete all booking fields.");
      return;
    }

    try {
      const res = await api.post("/api/appointment", {
        service_id: selectedService.id,
        date: selectedDate,
        start_time: selectedSlot,
        payment_method: paymentMethod,
      });

      setBookingMessage("✅ Appointment successfully booked! Redirecting...");
      setTimeout(() => {
        navigate("/patient");
      }, 2000); // ⏱ wait 2 seconds to show message then redirect
    } catch (err) {
      setBookingMessage(err.response?.data?.message || "Booking failed.");
    }
  };

  return (
    <div>
      <h3 className="mb-4">📅 Book an Appointment</h3>

      <div className="mb-3">
        <label className="form-label">Select a Date:</label>
        <input
          type="date"
          className="form-control"
          value={selectedDate}
          onChange={handleDateChange}
        />
      </div>

      {loading && <LoadingSpinner message="Loading available services..." />}
      {error && <div className="alert alert-danger">{error}</div>}

      {services.length > 0 && (
        <div className="mt-4">
          <h5>Available Services:</h5>
          <ul className="list-group">
            {services.map((s) => (
              <li
                className="list-group-item d-flex justify-content-between align-items-center"
                key={`${s.id}-${s.type}`}
              >
                <div>
                  <strong>{s.name}</strong>
                  {s.type === "promo" && (
                    <div>
                      <span className="text-muted text-decoration-line-through">
                        ₱{s.original_price}
                      </span>{" "}
                      <span className="text-success">₱{s.promo_price}</span>{" "}
                      <span className="text-danger">
                        ({s.discount_percent}% off)
                      </span>
                    </div>
                  )}
                  {s.type === "special" && (
                    <div className="text-info">
                      ₱{Number(s.price).toLocaleString()}{" "}
                      <span className="text-muted ms-2">Special Service</span>
                    </div>
                  )}
                  {s.type === "regular" && (
                    <div className="text-secondary">
                      ₱{Number(s.price).toLocaleString()}
                    </div>
                  )}
                </div>
                <button
                  className="btn btn-primary btn-sm"
                  onClick={() => handleServiceSelect(s)}
                >
                  Select
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}

      {selectedService && (
        <div className="mt-4">
          <h5>🕑 Select a Time Slot for {selectedService.name}</h5>

          {availableSlots.length === 0 && (
            <p className="text-muted">No available slots.</p>
          )}
          <select
            className="form-select"
            value={selectedSlot}
            onChange={(e) => setSelectedSlot(e.target.value)}
          >
            <option value="">-- Select Time Slot --</option>
            {availableSlots.map((slot) => (
              <option key={slot} value={slot}>
                {slot}
              </option>
            ))}
          </select>

          <div className="mt-3">
            <label className="form-label">Payment Method:</label>
            <select
              className="form-select"
              value={paymentMethod}
              onChange={(e) => setPaymentMethod(e.target.value)}
            >
              <option value="cash">Cash (on-site)</option>
              <option value="maya">Maya</option>
              <option value="hmo">HMO</option>
            </select>
          </div>

          <button
            className="btn btn-success mt-3"
            onClick={handleBookingSubmit}
          >
            Confirm Appointment
          </button>
          {bookingMessage && (
            <div className="alert alert-info mt-3">{bookingMessage}</div>
          )}
        </div>
      )}
    </div>
  );
}

export default BookAppointment;
