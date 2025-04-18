import React, { Component } from 'react'

class LangItem extends Component {
    render(){
        const {
            isActive,
            title,
            main
        } = this.props;
        return(
            <li ref={ (node) => this.LangItem = node } className={isActive ? 'active' : ''}>
                <span >{title}</span>
                <input
                    type='radio'
                    className = 'mainLang'
                    name = 'mainLang'
                    checked = { main }
                    title = 'Set as main language'
                    onChange = {()=>{}}
                    />
            </li>
        );
    }

    componentDidMount(){
        $(this.LangItem).on('click', (e)=>{
            e.stopPropagation();
            if(!$(e.target).hasClass('mainLang')){
                this.props.onClickLang(this.props.id);
            }else{
                this.props.change_main_lang(this.props.id);
            }
        });
    }
}

export default LangItem
