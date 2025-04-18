import { connect } from 'react-redux'
import LangsList from './LangList'

const mapStateToProps = function(store) {
    let langs = store.langs;
    return {
        langs
    };
};
const mapDispatchToProps = function(dispatch) {
    return {
        onClickLang: (id) => {
            dispatch({
                type: 'TOGGLE_LANG',
                id
            });
        },
        change_main_lang: (id) => {
            dispatch({
                type: 'CHANGE_MAIN_LANG',
                id
            });
        },
        fetchLangList(){
            let Promise = jQuery.ajax({
                method: 'GET',
                url: '/api/langs/'
            });
            Promise.done((data) => {
                if(data.langs){
                    dispatch({
                        type: 'FETCH_LANGS',
                        langs: data.langs
                    });
                }
            });
        }
    }
};
const Langs_container = connect(mapStateToProps, mapDispatchToProps)(LangsList);

export default Langs_container
