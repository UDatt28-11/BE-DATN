

export interface MessageReply {
  id: string;
  sender: "user" | "admin";
  content: string;
  createdAt: string;
}

export interface Message {
  id: string;
  userName: string;
  userAvatar?: string;
  accommodationName: string;
  content: string;
  createdAt: string;
  replies: MessageReply[];
  status: "Hiển thị" | "Ẩn";
}
