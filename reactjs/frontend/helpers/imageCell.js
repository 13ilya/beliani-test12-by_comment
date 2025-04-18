//imageCell.js
import DragDropBlock from './_fileDrop';
import React from 'react'
import ParamLink from './paramLink'
export default React.createClass({
    /**
        @param {object} params                      - data for image cell
            @param {int}    id                      - image cell id
            @param {int}    doc_id                  - images doc_id
            @param {str}    type                    - images type
            @param {str}    name                    - image cell name
            @param {bool}   show_delete_btn
            @param {bool}   show_param_image_name
            @param {bool}   show_param_image_doc_id
            @param {int}    index                   - image index in image table
            @param {object} drop_options            - options for drag and drop block
    **/
    render(){
        const params = this.props.params || {}
        const imageChangeCell = this.props.imageChangeCell || false;
        const multiple = this.props.multiple || false;
        const deleteSingleImage = this.props.deleteSingleImage || false;
        const fileLoad = this.props.fileLoad || false;
        const addImgBtnId = params.name + params.doc_id + params.type;
        return(
            <div className='imageCell'>
                <DragDropBlock
                    options={params.drop_options}
                    imageChangeCell={imageChangeCell}
                    uploader_link_selector = {'#'+addImgBtnId}
                    onFile={fileLoad}
                    multiple = {multiple}
                    >
                </DragDropBlock>
                <div className='imageControl'>
                    {params.show_delete_btn?
                        <a	href='#'
                            className={'deleteBtnClick'}
                            title='Delete image'
                            onClick={(e)=>{
                                e.preventDefault();
                                deleteSingleImage(params.doc_id,'item',params.type) }}>
                        </a>
                        :''
                    }
                    <a	href='#'
                        title='Upload image'
                        className='addNewBtnClick'
                        onClick={(e)=>{
                            e.preventDefault();
                            $('#'+addImgBtnId).click() }}></a>
                    {params.show_param_image_name?
                        <div>
                            <ParamLink text={'[[shop_img_'+name.toLowerCase()+'_'+params.index+']]'} asIcon={true} style={{zIndex:'10'}}/>
                        </div>:''}
                    {params.show_param_image_doc_id?
                        <div>
                            <ParamLink title='doc_ID' text={doc_id} asIcon={true} style={{right: '76px',    width: '59px'}}/>
                        </div>:''
                    }
                    <form
                        action='#'
                        method='post'
                        encType='multipart/form-data'
                        style={{display:'none'}}
                        onSubmit={(e)=>{
                            e.preventDefault();
                            let input = $('#'+addImgBtnId);
                            fileLoad(multiple ? input.prop('files'): input.prop('files')[0]);
                            }
                        }
                        >
                        <input
                            type='file'
                            id={addImgBtnId}
                            multiple = {multiple}
                            onChange={(e)=>{if(e.target.value!='')$('#'+addImgBtnId+'submit').click()}} />
                        <input type='submit' id={addImgBtnId+'submit'} />
                    </form>
                </div>
            </div>
        )
    }
})
