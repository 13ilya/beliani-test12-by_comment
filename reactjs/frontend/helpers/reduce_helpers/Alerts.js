function Alerts(state = {}, action) {
    let clone = Object.assign({}, state);
    switch (action.type) {
        case 'ALERT':
            clone = {
                show: action.show,
                text: action.text,
                alertType: action.alertType
            };
            return clone;
        case 'REMOVE_ALERT':
            console.log('[ALERT] Reducer ', action);
            clone='';
            return clone;
        default:
            return state;
    }
}

export default Alerts
