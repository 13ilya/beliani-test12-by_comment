import { combineReducers } from 'redux'
import { routerReducer } from 'react-router-redux'

import issueLog from './issueLog'
import issueLogSettings from './issueLogSettings'
import shipping_prices_monitor from './shipping_prices_monitor'
import issueLogFiles from './issueLogFiles'

import filters from '../../helpers/reduce_helpers/filters'
import ReactSpinner from '../../helpers/reduce_helpers/ReactSpinner'
import Comment_block from '../../helpers/reduce_helpers/comments_block'
import confirm from '../../helpers/reduce_helpers/Confirm'
import alerts from '../../helpers/reduce_helpers/Alerts'
import custom_data from '../../helpers/reduce_helpers/custom_data'

const rootReducer = combineReducers({
    ReactSpinner,
    Comment_block,
    shipping_prices_monitor,
    issueLogFiles,
    confirm,
    alerts,
    custom_data,
    issueLog,
    issueLogSettings,
    filters,
    routing: routerReducer
});

export default rootReducer
