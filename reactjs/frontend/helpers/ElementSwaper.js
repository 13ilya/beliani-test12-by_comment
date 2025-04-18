import React from 'react'
import FileDrop from 'react-file-drop'

export default React.createClass({
_handleFileDrop (params,callback,files,event) {
        $(event.target).removeClass('DropOver');
        const source = JSON.parse(event.dataTransfer.getData('text'));
        const target= params
        callback(target,source);
    },
_onDragOver(e){
    $(e.target).addClass('DropOver');
},
_onDragLeave(e){
    $(e.target).removeClass('DropOver');
},
startDrag(params,event){
   event.dataTransfer.setData('text', JSON.stringify(params));
  },
render() {
    const bsClass = this.props.bsClass?this.props.bsClass:'';
    const drag = this.props.drag!=undefined?this.props.drag:true;
    const params = this.props.params?this.props.params:{}
    const children = this.props.children?this.props.children:{}
    const callback = this.props.callback
    const onClickFunc = this.props.onClickFunc
    return (
        <div className='dropBox' draggable={drag}
            onDragStart={this.startDrag.bind(null,params)}
            onClick = {onClickFunc}
            >
            <FileDrop
                frame={document}
                onDrop={this._handleFileDrop.bind(null,params,callback)}
                onDragOver={this._onDragOver}
                onDragLeave={this._onDragLeave}
                className={bsClass}
                >
            </FileDrop>
            {children}
        </div>
        );
    }
});
