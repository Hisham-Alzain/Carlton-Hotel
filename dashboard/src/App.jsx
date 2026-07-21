import { useEffect } from 'react';
import { BrowserRouter, Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom';
import AppShell from './components/layout/AppShell.jsx';
import RequirePermission from './components/RequirePermission.jsx';
import Login from './pages/Login.jsx';
import Overview from './pages/Overview.jsx';
import Reservations from './pages/Reservations.jsx';
import ReservationDetail from './pages/ReservationDetail.jsx';
import OperationsQueue from './pages/OperationsQueue.jsx';
import Settings from './pages/Settings.jsx';
import NotFound from './pages/NotFound.jsx';
import FrontDesk from './pages/FrontDesk.jsx';
import ServiceRequests from './pages/ServiceRequests.jsx';
import SupportTickets from './pages/SupportTickets.jsx';
import SupportTicketDetail from './pages/SupportTicketDetail.jsx';
import AvailabilityCalendar from './pages/AvailabilityCalendar.jsx';
import DepartureServices from './pages/DepartureServices.jsx';
import RateGrid from './pages/RateGrid.jsx';
import CreateReservation from './pages/CreateReservation.jsx';
import HousekeepingBoard from './pages/HousekeepingBoard.jsx';
import FolioDetail from './pages/FolioDetail.jsx';
import NightAudit from './pages/NightAudit.jsx';
import GuestsList from './pages/GuestsList.jsx';
import GuestProfile from './pages/GuestProfile.jsx';
import Reports from './pages/Reports.jsx';
import Events from './pages/Events.jsx';
import EventDetail from './pages/EventDetail.jsx';
import { useAuthStore } from './store/authStore.js';
import { PERMISSIONS } from './utils/permissions.js';

const ProtectedRoute = () => {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const isBootstrapped = useAuthStore((state) => state.isBootstrapped);
  const location = useLocation();

  if (!isBootstrapped) {
    return <div className="app-loading">Restoring Carlton session...</div>;
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  return <Outlet />;
};

const RedirectHome = () => {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  return <Navigate to={isAuthenticated ? '/overview' : '/login'} replace />;
};

function App() {
  const isBootstrapped = useAuthStore((state) => state.isBootstrapped);
  const rehydrate = useAuthStore((state) => state.rehydrate);

  useEffect(() => {
    if (!isBootstrapped) void rehydrate();
  }, [isBootstrapped, rehydrate]);

  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route element={<ProtectedRoute />}>
          <Route element={<AppShell />}>
            <Route path="/overview" element={<Overview />} />
            <Route
              path="/front-desk"
              element={(
                <RequirePermission permission="reservations.view">
                  <FrontDesk />
                </RequirePermission>
              )}
            />
            <Route
              path="/reservations"
              element={(
                <RequirePermission permission="reservations.view">
                  <Reservations />
                </RequirePermission>
              )}
            />
            <Route
              path="/reservations/new"
              element={(
                <RequirePermission permission="reservations.create">
                  <CreateReservation />
                </RequirePermission>
              )}
            />
            <Route
              path="/reservations/:uuid"
              element={(
                <RequirePermission permission="reservations.view">
                  <ReservationDetail />
                </RequirePermission>
              )}
            />
            <Route
              path="/operations/queue"
              element={(
                <RequirePermission anyOf={['queue.view', 'service_requests.view', 'tickets.view']}>
                  <OperationsQueue />
                </RequirePermission>
              )}
            />
            <Route path="/operations/service-requests" element={<RequirePermission permission="service_requests.view"><ServiceRequests /></RequirePermission>} />
            <Route path="/operations/tickets" element={<RequirePermission permission="tickets.view"><SupportTickets /></RequirePermission>} />
            <Route path="/operations/tickets/:id" element={<RequirePermission permission="tickets.view"><SupportTicketDetail /></RequirePermission>} />
            <Route path="/operations/departures" element={<RequirePermission anyOf={['operations_queue.view','operations_queue.manage']}><DepartureServices /></RequirePermission>} />
            <Route path="/operations/night-audit" element={<RequirePermission permission={PERMISSIONS.FOLIOS_VIEW}><NightAudit /></RequirePermission>} />
            <Route path="/operations/housekeeping" element={<RequirePermission anyOf={['operations_queue.view','operations_queue.manage']}><HousekeepingBoard /></RequirePermission>} />
            <Route path="/reservations/availability" element={<RequirePermission permission="availability.view"><AvailabilityCalendar /></RequirePermission>} />
            <Route path="/reservations/:uuid/folio" element={<RequirePermission permission="folios.view"><FolioDetail /></RequirePermission>} />
            <Route path="/rates" element={<RequirePermission anyOf={['rates.view','rates.manage']}><RateGrid /></RequirePermission>} />
            <Route path="/guests" element={<RequirePermission permission="guests.view"><GuestsList /></RequirePermission>} />
            <Route path="/guests/:id" element={<RequirePermission permission="guests.view"><GuestProfile /></RequirePermission>} />
            <Route path="/reports" element={<RequirePermission permission="reports.view"><Reports /></RequirePermission>} />
            <Route path="/events" element={<RequirePermission permission={PERMISSIONS.EVENTS_VIEW}><Events /></RequirePermission>} />
            <Route path="/events/:id" element={<RequirePermission permission={PERMISSIONS.EVENTS_VIEW}><EventDetail /></RequirePermission>} />
            <Route path="/settings" element={<Settings />} />
          </Route>
        </Route>
        <Route path="/" element={<RedirectHome />} />
        <Route path="*" element={<NotFound />} />
      </Routes>
    </BrowserRouter>
  );
}

export default App;
