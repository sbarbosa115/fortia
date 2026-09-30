import '@shared/ui';
import './styles/theme.css';
import {createRoot} from 'react-dom/client';
import {App} from './App';
import {createRespondentI18n} from './i18n';

const root = document.getElementById('root');
if (root) {
  createRoot(root).render(<App i18n={createRespondentI18n()} />);
}
