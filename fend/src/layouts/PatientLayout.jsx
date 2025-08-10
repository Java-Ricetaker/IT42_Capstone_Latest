import { Outlet } from "react-router-dom";
import PatientNavbar from "../components/PatientNavbar";

function PatientLayout() {
  return (
    <div className="d-flex flex-column min-vh-100">
      <PatientNavbar />
      <main className="flex-grow-1 container py-4">
        <Outlet />
      </main>
    </div>
  );
}

export default PatientLayout;
