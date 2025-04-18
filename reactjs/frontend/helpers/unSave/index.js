import unSaveMarker from './unsaveMarker'
import { connect } from 'react-redux'

const mapStateToProps = function(store) {
    return {
       unSaved: store.unSave,
    };
};
const mapDispatchToProps = function(dispatch) {
    return {
		ClearUnsaved:()=>{dispatch({type: 'CLEAR_UNSAVE_BOX'});}
	}
};
const UnSave = connect(mapStateToProps,mapDispatchToProps)(unSaveMarker);

export default UnSave
