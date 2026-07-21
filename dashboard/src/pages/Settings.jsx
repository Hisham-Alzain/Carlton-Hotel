import { PageHeader, Card, Field, Select } from '../components/ui/Primitives.jsx';
import { useAuthStore } from '../store/authStore.js';

const Settings = () => {
  const { locale, setLocale } = useAuthStore();

  return (
    <>
      <PageHeader title="Profile settings" subtitle="Local prototype settings for language and direction testing." />
      <Card padded>
        <Field label="Dashboard language">
          <Select value={locale} onChange={(event) => setLocale(event.target.value)}>
            <option value="en">English</option>
            <option value="ar">Arabic</option>
          </Select>
        </Field>
      </Card>
    </>
  );
};

export default Settings;
