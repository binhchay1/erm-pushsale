import base from '../vi.js';
import filters from './filters.js';
import operations from './operations.js';
import dashboard from './dashboard.js';
import notifications from './notifications.js';
import pages from './pages.js';
import shipping from './shipping.js';
import integrations from './integrations.js';
import reports from './reports.js';
import org from './org.js';
import rankings from './rankings.js';
import profile from './profile.js';
import charts from './charts.js';
import nav from './nav.js';
import labels from './labels.js';
import { mergeLocales } from '../../merge.js';

export default mergeLocales(base, {
    nav,
    labels,
    filters,
    operations,
    dashboard,
    notifications,
    pages,
    shipping,
    integrations,
    reports,
    org,
    rankings,
    profile,
    charts,
});
