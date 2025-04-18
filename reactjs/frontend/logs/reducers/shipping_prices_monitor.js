export default function(state = [], action) {
    let clone = Object.assign([], state);
    switch (action.type) {
        case 'FETCH_SHIPPING_PRICES_MONITOR':
            clone = action.data;
            return clone;
        case 'CHANGE_SHIPPING_PRICES_MONITOR':
            clone[action.idx][action.fld] = action.value
            return clone;
        default:
            return state;
    }
}
