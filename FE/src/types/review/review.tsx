

export interface MediaItem {
  id: string;
  type: "image" | "video";
  url: string;
}

export interface Review {
  id: string;
  userName: string;
  userAvatar?: string;
  accommodationName: string;
  roomName?: string;
  rating: number; // 1–5
  comment: string;
  media: MediaItem[];
  createdAt: string;
  status: "Hiển thị" | "Ẩn";
}
