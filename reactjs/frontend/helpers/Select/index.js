//select.jsx
import React from 'react'
import Select from 'react-select'
import 'react-select/dist/react-select.css'
import PureRenderMixin from 'react-addons-pure-render-mixin'

/**
    @param {bool} search            - possibility to searcj in list
    @param {bool} clear             - possibility clear input string
    @param {string} SelectClass     - custom style for component
    @param {bool} value             - value for input
    @param {bool} optionsMap        - options list
    @param {bool} onChangeSelect    - callback when value selected
    @param {bool} multi             - possibility multicheck
    @param {bool} multi_v           - if true result = array else string
    @param {string} title_lang      - depricated
    @param {string} matchPos        - search from this position
    @param {func} clickFunc         - callback when open options list
    @param {func} openOnFocus       - callback when input focused
    @param {bool} autoBlur          - leave focus when value selected if true
    @param {func} onBlur            - call this function when select lost focus
    @param {func} valueRenderer	    - custom rendering for select value
**/
export default React.createClass({
  mixins:[PureRenderMixin],
  render(){
    let {
        search = false,
        clear = false,
        SelectClass = '',
        value = '',
        optionsMap = '',
        onChangeSelect = value => console.log(value),
        multi = false,
        multi_v = false,
        disabled = false,
        title_lang = '',
        matchPos = 'start',
        clickFunc,
        openOnFocus,
        autoBlur,
        onBlur,
        valueRenderer,
        onValueClick,
        draggable = false
    } = this.props;
    const dragFunctions = new this.DRAG_FUNCTIONS(multi_v,onChangeSelect);
    if(draggable && multi){
        valueRenderer = (data)=>{
            return (
                <div
                    className='draggable_value'
                    draggable ={true}
                    onDragStart = {dragFunctions._onDragStart}
                    onDragEnd = {dragFunctions._onDrop}
                    onDragEnter = {dragFunctions._onDragEnter}
                    onDragLeave = {dragFunctions._onDragLeave}
                    id = {data.value}
                    >{data.label}
                </div>
            )
        }
        if(!onValueClick) onValueClick = ()=>{}
    }
    return(
      <Select
        name='form-field-name'
        value={value}
        multi={multi}
        autoBlur={autoBlur}
        joinValues={multi_v}
        delimite=','
        onValueClick = {onValueClick}
        openOnFocus={openOnFocus}
        simpleValue={true}
        options = {optionsMap}
        searchable={search}
        clearable={clear}
        onBlur={onBlur}
        disabled = {disabled}
        valueRenderer = {valueRenderer}
        matchProp = 'label'
        matchPos = {matchPos}
        className={SelectClass}
        onOpen={clickFunc}
        onChange={(val)=>{
          if (multi_v)val=val.split(',');
          if (val.length==1)val=val[0];
          onChangeSelect(val,title_lang);
          }}
      />
    )
  },
    DRAG_FUNCTIONS(multi_v,onChangeSelect){
        this.id = '';
        this._onDragStart = (e)=>{
              this.id = '#' + e.target.id;
              $(e.target).parents('.Select-value').addClass('dragged').css({borderColor:'#a94442'});
              e.dataTransfer.setData('text/plain',JSON.stringify({id:this.id}));
        };
        this._onDrop = (e)=>{
            $(this.id).parents('.Select-value').removeAttr('style').removeClass('dragged');
            var values_arr = '';
            console.log($('.Select-value'));
            $(e.target).parents('.Select-control').find('.Select-value').each((idx,elm)=>{
                values_arr+=$(elm).find('.draggable_value').attr('id') + ',';
            })
            console.log('values_arr',values_arr);
            values_arr = values_arr.substring(0,values_arr.length-1);
            if (multi_v)values_arr=values_arr.split(',');
            if (values_arr.length==1)values_arr=values_arr[0];
            onChangeSelect(values_arr,'');
        };
        this._onDragLeave = ()=>{

        };
        this._onDragEnter = (e)=>{

            var root_level = $(e.target).parents('.Select-control'),
                draggedElem = $(root_level).find(this.id).parents('.Select-value'),
                currentElem = $(e.target).parents('.Select-value'),
                dragged_index = $('.Select-value').index(draggedElem),
                current_index = $('.Select-value').index(currentElem);

            if (this.id == '#'+e.target.id ){
                return true;
            }

            if(dragged_index < current_index){
                currentElem.after(draggedElem);
            }else{
                currentElem.before(draggedElem);
            }
        }
    }
})
