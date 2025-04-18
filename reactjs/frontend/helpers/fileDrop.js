//fileDrop.js
import React from 'react'
import FileDrop from 'react-file-drop'

export default React.createClass({

_handleFileDrop (id,block_name,struct,onFile,imageChangeCell,files,event) {
        $(event.target).removeClass('DropOver');
        let inputFile=files[0];
        if(inputFile) onFile(struct['hash_'+block_name],id,inputFile,block_name,struct);
        else{
            let source = JSON.parse(event.dataTransfer.getData('text'));
            let target={
                    row_id:struct.doc_id,
                    cell_id: block_name,
                    path:struct['path_'+block_name],
                    hash:struct['hash_'+block_name],
                    origial:struct['original_'+block_name],
                    dimensions: struct.dimensions,
                    primary: struct.primary,
                    details: struct.details
                }
            if((target.row_id==source.row_id) && (target.cell_id==source.cell_id)) return true;
            imageChangeCell(id,target,source);
        }

    },
_onDragOver(e){
    $(e.target).addClass('DropOver');
},
_onDragLeave(e){
    $(e.target).removeClass('DropOver');
},
startDrag(struct,block_name,event){
    var data = {
                row_id:struct.doc_id,
                cell_id: block_name,
                path:struct['path_'+block_name],
                hash:struct['hash_'+block_name],
                origial:struct['original_'+block_name],
                dimensions: struct.dimensions,
                primary: struct.primary,
                details: struct.details
            };
   event.dataTransfer.setData('text', JSON.stringify(data));
  },
render() {
    const path = this.props.path?this.props.path:'/images/react/no_image.png';
    const bsClass = this.props.bsClass;
    const id =this.props.master_id;
    const ext =this.props.ext;
    const block_name =this.props.cell_id;
    const struct =this.props.options;
    const original = this.props.original || struct['original_'+block_name] || '';
    const onFile =this.props.onFile;
    const imageChangeCell =this.props.imageChangeCell;
    const ClickAction =this.props.ClickAction;
    return (
        <div className='dropBox' draggable={ext?true:false}
            onClick={()=>{
                console.log(original);
                if(!this.props.path)$(ClickAction).click();
                else window.open(original,original);
            }}
            onDragStart={this.startDrag.bind(null,struct,block_name)}>
                <FileDrop
                    frame={document}
                    onDrop={this._handleFileDrop.bind(null,id,block_name,struct,onFile,imageChangeCell)}
                    onDragOver={this._onDragOver}
                    onDragLeave={this._onDragLeave}
                    className={bsClass}>
                </FileDrop>
                <img src={path}/>
        </div>
        );
    }
});
