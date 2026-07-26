import { Navigate } from 'react-router-dom';
import { useAuthStore } from '../store/authStore.js';
import { hasAnyPermission, hasPermission } from '../utils/permissions.js';

const RequirePermission = ({ permission, anyOf, children }) => {
  const user = useAuthStore((state) => state.user);
  const allowed = anyOf ? hasAnyPermission(user, anyOf) : hasPermission(user, permission);

  if (!allowed) {
    return <Navigate to="/overview" replace />;
  }

  return children;
};

export default RequirePermission;
