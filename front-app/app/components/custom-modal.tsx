import { Modal, TitleBar, useAppBridge } from '@shopify/app-bridge-react';

interface IProps {
  id: string;
  title: string;
  content: React.ReactNode;
  children?: React.ReactNode;
}

export function CustomModal({ id, title, content, children }: IProps) {
  const shopify = useAppBridge();

  return (
    <>
      <button 
        onClick={(e) => {
          e.preventDefault()
          shopify.modal.show(id)
        }}
      >
        {children ? children : "Open Modal"}
      </button>
      <Modal id={id} variant="base">
        <div className="p-[16px]">
          {content}
        </div>
        <TitleBar title={title}>
          {/* <h3 className="text-[20px] font-bold">What is a Trackpoint ID and where to find it?</h3> */}
          {/* <div>What is a Trackpoint ID and where to find it?</div> */}
          {/* <button variant="primary">Label</button> */}
          {/* <button onClick={() => shopify.modal.hide('my-modal')}>Label</button> */}
        </TitleBar>
      </Modal>
    </>
  );
}
