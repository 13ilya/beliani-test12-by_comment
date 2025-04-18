export default function(state = {}, action) {
    let clone = Object.assign({}, state);
    switch (action.type) {
        case 'FETCH_ISSUE_LOG_IMAGES':
            clone.images = action.images;
            clone.files = action.files;
            return clone;
        default:
            return state;
    }
}
