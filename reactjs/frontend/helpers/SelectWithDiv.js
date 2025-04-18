//select.jsx
import React from 'react'
import Select from 'react-select'
import 'react-select/dist/react-select.css'
import PureRenderMixin from 'react-addons-pure-render-mixin'

//<Select
//    optionsMap={colorsMap}
//    value={top_left_text_color}
//    onChangeSelect={onChangeBannerInput}
//    title_lang='top_left_text_color'
//    valueComponent={<div></div>}
//    SelectClass='has-value Select--single Select-margin'
//    clear={false}
//    search={false}
///>


const GravatarOption = React.createClass({
    propTypes: {
        children: React.PropTypes.node,
        className: React.PropTypes.string,
        isDisabled: React.PropTypes.bool,
        isFocused: React.PropTypes.bool,
        isSelected: React.PropTypes.bool,
        onFocus: React.PropTypes.func,
        onSelect: React.PropTypes.func,
        option: React.PropTypes.object.isRequired,
    },
    handleMouseDown (event) {
        event.preventDefault();
        event.stopPropagation();
        this.props.onSelect(this.props.option, event);
    },
    handleMouseEnter (event) {
        this.props.onFocus(this.props.option, event);
    },
    handleMouseMove (event) {
        if (this.props.isFocused) return;
        this.props.onFocus(this.props.option, event);
    },
    render () {
        return (
            <div className={this.props.className}
                onMouseDown={this.handleMouseDown}
                onMouseEnter={this.handleMouseEnter}
                onMouseMove={this.handleMouseMove}
                title={this.props.option.title}>
                <div style={{backgroundColor:this.props.children, width:'20px',height:'20px', marginRight:'20px',display:'inline-block'}}></div>
                <span style={{display:'inline-block',verticalAlign:'top',lineHeight:'25px'}}>
                    {this.props.children}
                </span>
            </div>
        );
    }
});

const GravatarValue = React.createClass({
    propTypes: {
        children: React.PropTypes.node,
        placeholder: React.PropTypes.string,
        value: React.PropTypes.object
    },
    render () {
        return (
            <div className='Select-value' title={this.props.value.title}>
                <span className='Select-value-label'>
                    <div style={{backgroundColor:this.props.children, width:'15px',height:'15px',float:'left', marginRight:'20px', marginTop:'3px'}}></div>
                    {this.props.children}
                </span>
            </div>
        );
    }
});


export default React.createClass({
    mixins:[PureRenderMixin],
    render(){
        const search = this.props.search?this.props.search:false;
        const clear = this.props.clear?this.props.clear:false;
        const SelectClass = this.props.SelectClass?this.props.SelectClass:'';
        const value = this.props.value?this.props.value:'';
        const optionsMap = this.props.optionsMap?this.props.optionsMap:[];
        const onChangeSelect = this.props.onChangeSelect?this.props.onChangeSelect:false;
        const multi = this.props.multi?this.props.multi:false;
        const multi_v = this.props.multi_v?this.props.multi_v:false;
        const title_lang = this.props.title_lang?this.props.title_lang:'';

        return(
            <Select
                name='form-field-name'
                value={value}
                multi={multi}
                joinValues={multi_v}
                delimite=','
                simpleValue={true}
                options = {optionsMap}
                searchable={search}
                clearable={clear}
                matchProp = 'label'
                matchPos = 'start'
                optionComponent={GravatarOption}
                valueComponent={GravatarValue}
                className={SelectClass}
                onChange={(val)=>{
                    if (multi_v)val=val.split(',');
                    if (val.length==1)val=val[0];
                    onChangeSelect(val,title_lang);
                    }}
            />
        )
    }
})
