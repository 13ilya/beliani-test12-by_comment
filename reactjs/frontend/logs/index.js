import './style.scss'
import React from 'react'
import { Router, Route,IndexRoute} from 'react-router'
import { render } from 'react-dom'
import { Provider } from 'react-redux'

import store, { history } from './store'
import Main from './components/Main'
import IssueLogs from './components/pages/issue_logs'
import Issue_log_page from './components/pages/issue_logs/issue_page'

const router = (
    <Router history={history}>
        <Route path='/react/logs/' component={Main}>
            <IndexRoute component={IssueLogs} />
            <Route path='issue_logs/' component={IssueLogs} />
            <Route path='issue_logs/:id/' component={Issue_log_page} />
        </Route>
    </Router>
);

render(
    <Provider store={store}>
        {router}
    </Provider>,
    document.getElementById('app')
);
