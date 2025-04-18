function Confirm(state = {}, action) {
    let clone = Object.assign({}, state);
    switch (action.type) {
        case 'CONFIRM':
            clone = {
                text: action.text,
                callback: action.callback,
                confirmType: action.confirmType
            };
            return clone;
        case 'REMOVE_CONFIRM':
            clone='';
            return clone;
        default:
            return state;
    }
}

export default Confirm
