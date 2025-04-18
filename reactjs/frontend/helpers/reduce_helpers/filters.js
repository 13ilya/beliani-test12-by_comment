var initialState = {
    filterActive:'0',
    data:{},
    options:{},
    fetchedOptions:false
}
function filters(state = initialState, action) {
    let clone = Object.assign({}, state);
    switch (action.type) {
        case 'FILTERS_FETCH_OPTIONS':
                clone['options'] = Object.assign({}, clone['options'], action.options);
                clone['fetchedOptions'] = true;
            return clone;
        case 'FILTER_CHANGE':
                clone['data'][action.fld] = action.value;
            return clone;
        case 'FILTER_DEFAULTS':
                clone['data'] =  Object.assign({}, state['data'], action.params);
            return clone;
        default:
            return state;
    }
}
export default filters;
