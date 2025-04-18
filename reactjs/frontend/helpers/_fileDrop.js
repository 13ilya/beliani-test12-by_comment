//fileDrop.js
import React from 'react'
import FileDrop from 'react-file-drop'

export default React.createClass({

_handleFileDrop (struct,onFile,imageChangeCell,multiple,files,event) {
        $(event.target).removeClass('DropOver');
        let inputFile = multiple ? files : files[0];
        if(inputFile) onFile(inputFile,struct);
        else{
            let source = JSON.parse(event.dataTransfer.getData('text'));
            let target = struct
            if(target.id==source.id) return true;
            imageChangeCell(target,source);
        }
    },
_onDragOver(e){
    $(e.target).addClass('DropOver');
},
_onDragLeave(e){
    $(e.target).removeClass('DropOver');
},
startDrag(options,event){
   event.dataTransfer.setData('text', JSON.stringify(options));
},
render() {
    /**
        @param {object} options
            @param {str} path                           - path for image
            @param {str} original                       - path for big image
            @param {str} id                             - image id
            @param {str} draggable                      - image draggable flag
            @param {str} bsClass                        - image draggable bsClass

        @param {str} uploader_link_selector         - jquery selector for uploader button
    **/

    const options = this.props.options || {};
    const onFile =this.props.onFile;
    const multiple =this.props.multiple;
    const uploader_link_selector =this.props.uploader_link_selector || '';
    const imageChangeCell =this.props.imageChangeCell;
    return (
        <div className='dropBox' draggable={options.draggable || false}
            onClick={()=>{
                if(!options.path)$(uploader_link_selector).click();
                else window.open(options.original,options.original);
            }}
            onDragStart={this.startDrag.bind(null,options)}>
                <FileDrop
                    frame={document}
                    onDrop={this._handleFileDrop.bind(null,options,onFile,imageChangeCell,multiple)}
                    onDragOver={this._onDragOver}
                    onDragLeave={this._onDragLeave}
                    className={options.bsClass}>
                </FileDrop>
                <img
                    className = 'dropBoxImg'
                    src={options.path || '/images/react/no_image.png'}
                    />
        </div>
        );
    }
});
