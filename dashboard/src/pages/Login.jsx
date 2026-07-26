import { useState } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { Button, Field, Input, Select } from '../components/ui/Primitives.jsx';
import { useAuthStore } from '../store/authStore.js';

const Login = () => {
  const location = useLocation();
  const { login, isAuthenticated, isLoading, error, locale, setLocale } = useAuthStore();
  const [email, setEmail] = useState('admin@carlton.test');
  const [password, setPassword] = useState('demo1234');

  if (isAuthenticated) {
    return <Navigate to={location.state?.from?.pathname || '/overview'} replace />;
  }

  const submit = async (event) => {
    event.preventDefault();
    await login({ email, password });
  };

  return (
    <div className="login-page" dir={locale === 'ar' ? 'rtl' : 'ltr'}>
      <section className="login-visual">
        <div className="login-brand-mark" aria-hidden="true"><span /></div>
        <p className="login-kicker">Carlton Hotel Damascus</p>
        <h1>Property operations, handled with quiet precision.</h1>
        <p>A permission-aware staff workspace for arrivals, guest care, live requests, and folio alerts.</p>
        <div className="login-proof">
          <span>Reception desk</span>
          <span>Housekeeping line</span>
          <span>Concierge care</span>
        </div>
      </section>

      <form className="login-panel" onSubmit={submit}>
        <div>
          <p className="panel-kicker">Secure Staff Desk</p>
          <h2>Staff sign in</h2>
          <p className="login-help">Demo accounts: admin@carlton.test, reception@carlton.test, ops@carlton.test. Password: demo1234.</p>
        </div>
        {error && <div className="alert">{error.message}</div>}
        <Field label="Language">
          <Select value={locale} onChange={(event) => setLocale(event.target.value)}>
            <option value="en">English</option>
            <option value="ar">Arabic</option>
          </Select>
        </Field>
        <Field label="Email">
          <Input type="email" value={email} onChange={(event) => setEmail(event.target.value)} />
        </Field>
        <Field label="Password">
          <Input type="password" value={password} onChange={(event) => setPassword(event.target.value)} />
        </Field>
        <Button type="submit" isLoading={isLoading}>Enter staff desk</Button>
      </form>
    </div>
  );
};

export default Login;
