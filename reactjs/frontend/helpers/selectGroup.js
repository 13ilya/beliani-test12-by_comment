//selectGroup.jsx
import React from 'react'
import PureRenderMixin from 'react-addons-pure-render-mixin'
import Select from '../helpers/Select'

export default React.createClass({
    mixins:[PureRenderMixin],
    render(){
        const search=this.props.search || false;
        const clear=this.props.clear || false;
        const value=this.props.value  || '';
        const list=this.props.list || [];
        const multi_v=this.props.multi_v || false;
        const func=this.props.func || false;
        const label=this.props.label || false;
        const bsClass=this.props.bsClass || '';

        return(
            <div className={'input-group '+bsClass}>
                <span className='input-group-addon'>{label}</span>
                <Select
                    search={search}
                    clear={clear}
                    SelectClass='inputGroupAddon'
                    value={value}
                    optionsMap={list}
                    onChangeSelect={func}
                    multi_v={multi_v}
                />
            </div>
        )
    }
})
