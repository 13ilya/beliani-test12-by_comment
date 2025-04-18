import MulangFields from './mulangFields'
import { connect } from 'react-redux'

const mapStateToProps = function(store) {
    return {
        langs: store.langs
    };
};
const MulangFields_container = connect(mapStateToProps)(MulangFields);

export default MulangFields_container
