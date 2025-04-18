var initialState = {}
function custom_data(state = initialState, action) {
    let clone = Object.assign({}, state);
    switch (action.type) {
        case 'CUSTOM_DATA_FETCH':
            clone[action.block] = action.value;
            return clone;
        case 'CUSTOM_DATA_CHANGE':
            if(!clone[action.block]) clone[action.block] = {}
            clone[action.block][action.fld] = action.value;
            return clone;
        case 'CUSTOM_DATA_CLEAR':
            action.block.forEach((block_data)=>{
                if(!block_data.fields){
                    delete clone[block_data.name];
                }else{
                    block_data.fields.forEach((field_name)=>{
                        delete clone[block_data.name][field_name];
                    })
                }
            });

            return clone;
        default:
            return state;
    }
}
export default custom_data;
