import ReactSpinner from './ReactSpinner'
import { connect } from 'react-redux'

const mapStateToProps = function(store) {
    let ReactSpinner = store.ReactSpinner;
    return {
        stopped: ReactSpinner._spinnerStopped,
        config: {position:'fixed'}
    };
};
const ReactSpinner_container = connect(mapStateToProps)(ReactSpinner);

export default ReactSpinner_container