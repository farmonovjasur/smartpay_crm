import { useSelector } from 'react-redux';

/**
 * Faqat berilgan rol(lar)ga ega foydalanuvchiga kontentni ko'rsatadi.
 * @param {{ roles: string|string[], children: React.ReactNode, fallback?: React.ReactNode }} props
 */
export function RoleGate({ roles, children, fallback = null }) {
  const user = useSelector((s) => s.auth.user);
  const allowed = Array.isArray(roles) ? roles.includes(user?.role) : user?.role === roles;
  return allowed ? children : fallback;
}
