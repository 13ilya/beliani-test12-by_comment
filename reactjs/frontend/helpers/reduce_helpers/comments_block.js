let initial_state={
    'data': {
                'responsible': ''
            },
    'options': {
            'comments': [],
            'logs': [],
            'users': [],
            'alarm':{},
            other_params:{}
    }
}
function Comment_block(state=initial_state, action) {
    let clone=Object.assign({}, state);
    switch (action.type) {
        case 'FETCH_COMMENT_BLOCK':
            clone.options.comments = action.data || []
            clone.options.logs = action.logs || []
            clone.options.alarm = action.alarm || []
            clone.options.other_params = action.other_params || {}
        return clone;
        case 'ALARM_CHANGE':
            clone.options.alarm[action.fld] = action.value || '';
        return clone;
        case 'FETCH_COMMENT_BLOCK_USERS':
            clone.options.users = action.data || []
        return clone;
        case 'DELETE_COMMENT':
            clone.options.comments = _.filter(clone.options.comments, ( comment ) => { return comment.id != id })
        return clone;
        case 'COMMENT_CHANGE':
            if (action.block!='data')clone[action.block][action.fld][action.subFld]=action.value;
            else clone[action.block][action.fld]=action.value;
        return clone;
        default:
            return state
    }
}

export default Comment_block
