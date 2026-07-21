import { Link } from 'react-router-dom';
import { Button, EmptyState } from '../components/ui/Primitives.jsx';

const NotFound = () => (
  <EmptyState
    title="Page not found"
    message="The requested Carlton dashboard area does not exist in this prototype."
    action={<Link to="/overview"><Button>Return to overview</Button></Link>}
  />
);

export default NotFound;
