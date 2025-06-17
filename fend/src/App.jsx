import { useEffect } from 'react';
import AppRouter from './router';
import { getFingerprint } from './utils/getFingerprint';

function App() {
  useEffect(() => {
    getFingerprint();
  }, []);
  return <AppRouter />;
}

export default App;
