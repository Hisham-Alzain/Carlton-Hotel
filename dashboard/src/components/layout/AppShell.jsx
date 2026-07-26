import { NavLink, Outlet } from 'react-router-dom';
import { BarChart2, BedDouble, Bell, Calendar, CalendarDays, CalendarRange, ClipboardCheck, ClipboardList, ConciergeBell, LayoutDashboard, LogOut, MapPin, MessageCircle, PlusCircle, Settings, TrendingUp, Users, Wrench } from 'lucide-react';
import { useAuthStore } from '../../store/authStore.js';
import { useDashboardStore } from '../../store/dashboardStore.js';
import { canAccessNavItem, PERMISSIONS } from '../../utils/permissions.js';
import { Button } from '../ui/Primitives.jsx';

const navGroups = [
  {
    title: 'Operations',
    items: [
      { label: 'Overview', path: '/overview', icon: LayoutDashboard },
      { label: 'Front Desk', path: '/front-desk', icon: ClipboardList, permission: 'reservations.view' },
      { label: 'Live Queue', path: '/operations/queue', icon: ConciergeBell, anyOf: ['queue.view', 'service_requests.view', 'tickets.view'] },
      { label: 'Reservations', path: '/reservations', icon: CalendarDays, permission: 'reservations.view' },
      { label: 'New Booking', path: '/reservations/new', icon: PlusCircle, permission: 'reservations.create' },
      { label: 'Rate Grid', path: '/rates', icon: TrendingUp, anyOf: ['rates.view','rates.manage'] },
{ label: 'Service Requests', path: '/operations/service-requests', icon: Wrench, permission: 'service_requests.view' },
      { label: 'Support Tickets', path: '/operations/tickets', icon: MessageCircle, permission: 'tickets.view' },
      { label: 'Departures', path: '/operations/departures', icon: LogOut, anyOf: ['operations_queue.view','operations_queue.manage'] },
      { label: 'Night Audit', path: '/operations/night-audit', icon: ClipboardCheck, permission: PERMISSIONS.FOLIOS_VIEW },
      { label: 'Housekeeping', path: '/operations/housekeeping', icon: BedDouble, anyOf: ['operations_queue.view','operations_queue.manage'] },
      { label: 'Availability', path: '/reservations/availability', icon: CalendarRange, permission: 'availability.view' },
    ],
  },
  {
    title: 'Guests',
    items: [
      { label: 'Guests', path: '/guests', icon: Users, permission: 'guests.view' },
    ],
  },
  {
    title: 'Sales & Reports',
    items: [
      { label: 'Reports', path: '/reports', icon: BarChart2, permission: 'reports.view' },
      { label: 'Events', path: '/events', icon: Calendar, anyOf: ['events.view', 'events.manage'] },
    ],
  },
  {
    title: 'Workspace',
    items: [
      { label: 'Settings', path: '/settings', icon: Settings },
    ],
  },
];

const AppShell = () => {
  const { user, logout, locale, setLocale } = useAuthStore();
  const totalRooms = useDashboardStore((s) => s.summary?.occupancy?.total_rooms);
  const direction = locale === 'ar' ? 'rtl' : 'ltr';

  return (
    <div className="app-shell" dir={direction}>
      <aside className="sidebar">
        <div className="brand">
          <div className="brand-mark" aria-hidden="true">
            <span />
          </div>
          <div className="brand-name">
            <strong>Carlton</strong>
            <span>Hotel Damascus</span>
          </div>
        </div>

        <div className="property-card">
          <span>Today</span>
          <strong>June 28</strong>
          <small>{totalRooms ? `${totalRooms} rooms` : 'Property'}</small>
        </div>

        <nav>
          {navGroups.map((group) => (
            <div className="nav-group" key={group.title}>
              <p className="nav-title">{group.title}</p>
              {group.items.filter((item) => canAccessNavItem(item, user)).map((item) => (
                <NavLink key={item.path} to={item.path} className="nav-link">
                  <item.icon size={17} />
                  <span>{item.label}</span>
                </NavLink>
              ))}
            </div>
          ))}
        </nav>
      </aside>

      <div className="shell-main">
        <header className="topbar">
          <div className="topbar-title">
            <strong><MapPin size={16} /> Damascus property desk</strong>
            <span>{user?.name} · {user?.type === 'super_admin' ? 'Super admin' : 'Staff'}</span>
          </div>
          <div className="topbar-actions">
            <Button variant="ghost" icon={Bell} aria-label="Open notifications">3</Button>
            <select className="select shell-select" value={locale} onChange={(event) => setLocale(event.target.value)} aria-label="Language">
              <option value="en">EN</option>
              <option value="ar">AR</option>
            </select>
            <Button variant="ghost" icon={LogOut} onClick={logout}>Logout</Button>
          </div>
        </header>
        <main className="content">
          <Outlet />
        </main>
      </div>
    </div>
  );
};

export default AppShell;
