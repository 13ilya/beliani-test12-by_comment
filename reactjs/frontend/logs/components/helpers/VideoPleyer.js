import React from 'react';
import ReactPlayer from 'react-youtube'

export default class video extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
        is_open:false
    }
  }
  render() {
    const {
        videoId = 'psR5nMAUPjw',
        width = '600px',
        height = '400px',
        autoplay = 0
    } = this.props.options,
    {
        is_open = false
    } = this.state || {}
    return (
        <div>
            {
                is_open ?
                    <div style={{
                            position:'fixed',
                            top:'0px',
                            left:'0px',
                            right:'0px',
                            bottom:'0px',
                            backgroundColor:'rgba(0,0,0,0.5)'
                        }}
                        onClick = {this.toggleVideo.bind(this,is_open)}
                        >
                        <div style={{
                                width,
                                height,
                                margin:'auto',
                                position: 'absolute',
                                top: '0px',
                                bottom: '0px',
                                left: '0px',
                                right: '0px'
                            }}>
                            <ReactPlayer
                                videoId={videoId}
                                opts = {{
                                    playerVars: {
                                        autoplay,
                                        rel : 0 
                                    }
                                }}
                                />
                        </div>
                    </div>:''
            }
            <div onClick = {this.toggleVideo.bind(this,is_open)}>
                <img style = {{width:'100%',cursor:'pointer'}}
                    src={'//img.youtube.com/vi/' + videoId + '/0.jpg'}/>
            </div>
        </div>
    );
  }
  toggleVideo(is_open){
      this.setState({is_open:!is_open})
  }
}
