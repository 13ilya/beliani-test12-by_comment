import React, { Component } from 'react'
import LangItem from './LangItem'

class LangList extends Component {
    componentWillMount(){
        this.props.fetchLangList();
    }
    componentDidMount(){
        $('body').on('click', ()=>{
            $(this.dropdown).removeClass('open');
        });
        $(this.button).on('click', (e)=>{
            e.stopPropagation();
            $(this.dropdown).toggleClass('open');
        });
    }
    render() {
        return(
            <div className='b-dropdown-langs'>
                <div className='btn-group' ref={ (node) => this.dropdown = node }>
                    <button
                        type='button'
                        className='multiselect dropdown-toggle btn btn-default'
                        ref={ (node) => this.button = node }
                    >
                        <span className='multiselect-selected-text'>Languages </span>
                        <b className='caret'>{''}</b>
                    </button>
                    <ul className='multiselect-container dropdown-menu pull-right'>
                        {this.props.langs.map((lang,idx) =>
                            <LangItem
                                {...lang}
                                onClickLang={this.props.onClickLang}
                                key={idx}
                                change_main_lang = {this.props.change_main_lang}
                            />
                        )}
                    </ul>
                </div>
            </div>
        )
    }

}

export default LangList
