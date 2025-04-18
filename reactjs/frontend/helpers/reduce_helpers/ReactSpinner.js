var initial_state = {
    _spinnerStopped: true
}
function ReactSpinner(state = initial_state, action) {
    let clone;
    switch (action.type) {
        case 'SPINNER':
            console.log('[SPINNER] Reducer ', action);
            clone = Object.assign({}, state);
            clone._spinnerStopped = action._spinnerStopped;
            return clone;
        default:
            return state;
    }
}

export default ReactSpinner
