import { createStore , applyMiddleware } from 'redux'
import { syncHistoryWithStore  } from 'react-router-redux'
import { browserHistory } from 'react-router'
import rootReducer from './reducers'
import createLogger from 'redux-logger'
import {routerMiddleware} from 'react-router-redux'

const logger = createLogger();
const middleware = routerMiddleware(browserHistory)

const store = createStore(rootReducer,applyMiddleware(logger),applyMiddleware(middleware));

export const history = syncHistoryWithStore(browserHistory, store);
export default store

