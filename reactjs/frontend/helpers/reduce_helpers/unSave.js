let initial_state = {}
function unSave (state = initial_state , action) {
  let clone = Object.assign({}, state)
  let deep1 = action.deep1 != undefined ? action.deep1 : ''
  let deep2 = action.deep2 != undefined ? action.deep2 : ''
  let field = action.field != undefined ? action.field : ''
  let oldValue = action.oldValue != undefined ? action.oldValue : ''
  let name = action.name != undefined ? action.name : ''
  if (clone[name] == undefined)clone[name] = {}
  if (deep1 && !clone[name][deep1]) clone[name][deep1] = {}
  if (deep2 && !clone[name][deep1][deep2])clone[name][deep1][deep2] = {}
  switch (action.type) {
    case 'CLEAR_UNSAVE_BOX':
      clone = {}
      return clone
    case 'BLOCK_SAVE':
      for (let key in clone)
        if (key.indexOf(name) != -1)delete clone[key]
      return clone
    case 'BLOCK_CHANGE':
        if (deep2 && clone[name][deep1][deep2][field]===undefined){
            clone[name][deep1][deep2][field] = oldValue
            return clone
        }
        else if (deep1 && clone[name][deep1][field]===undefined) {
            if(deep2) return clone
            clone[name][deep1][field] = oldValue
            return clone
        }
        else if (clone[name][field]===undefined){
            if(deep1 || deep2) return clone
            clone[name][field] = oldValue
        }
        return clone
    default:
      return state
  }
}
export default unSave
