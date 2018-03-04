
<?php
namespace Napol;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\Level;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\tile\Chest;
class ChestGuard extends PluginBase implements Listener {
    /** @var PocketGuardDatabaseManager  */
    private $databaseManager;
    private $queue;
    /** @var  PocketGuardLogger */
    private $chestGuardLogger;
    // Constants
    const NOT_LOCKED = -1;
    const NORMAL_LOCK = 0;
    const PASSCODE_LOCK = 1;
    const PUBLIC_LOCK = 2;
    public function onLoad()
	{
	}
	public function onEnable()
	{
        @mkdir($this->getDataFolder());
        $this->queue = [];
        $this->chestGuardLogger = new PocketGuardLogger($this->getDataFolder() . 'ChestGuard.log');
        $this->databaseManager = new PocketGuardDatabaseManager($this->getDataFolder() . 'ChestGuard.sqlite3');
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }
	public function onDisable()
	{
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $args)
	{
        if (!($sender instanceof Player)) {
            $sender->sendMessage("§cต้องใช้งานในเกมเท่านั้น!");
            return true;
        }
        if (isset($this->queue[$sender->getName()])) {
            $sender->sendMessage("You have already had the task to execute!");
            return true;
        }
        switch (strtolower($command->getName())) {
            case "cg":
                $option = strtolower(array_shift($args));
                switch ($option) {
                    case "lock":
                    case "unlock":
                    case "public":
                    case "info":
                        $this->queue[$sender->getName()] = [$option];
                        break;
                    case "passlock":
                    case "passunlock":
                        if (is_null($passcode = array_shift($args))) {
                            $sender->sendMessage("§aใช้: §e/cg passlock §b<passcode>");
                            return true;
                        }
                        $this->queue[$sender->getName()] = [$option, $passcode];
                        break;
                    case "share":
                        if (is_null($target = array_shift($args))) {
                            $sender->sendMessage("§aใช้: §e/cg share §b<player>");
                            return true;
                        }
                        $this->queue[$sender->getName()] = [$option, $target];
                        break;
                    default:
                        $sender->sendMessage("§e/cg §cไม่พบคำสั่ง §b$option §c!");
                        $sender->sendMessage("§e/cg §b<lock | unlock | public | info>");
                        $sender->sendMessage("§e/cg §b<passlock | passunlock | share>");
                        return true;
                }
                $this->chestGuardLogger->log("[" . $sender->getName() . "] Action:Command Command:" . $command->getName() . " Args:" . implode(",", $args));
                $sender->sendMessage("§7(§a" .$option."§7) §aแตะที่กล่องของคุณ!");
                return true;
            case "scg":
                $option = strtolower(array_shift($args));
                switch ($option) {
                    case "unlock":
                        $unlockOption =strtolower(array_shift($args));
                        switch ($unlockOption) {
                            case "a":
                            case "all":
                                $this->databaseManager->deleteAll();
                                $sender->sendMessage("§f-> §aปลดล็อกกล่องทั้งหมดแล้ว§c!");
                                break;
                            case "p":
                            case "player":
                                $target = array_shift($args);
                                if (is_null($target)) {
                                    $sender->sendMessage("§f-> §cต้องการชื่อผู้เล่น!");
                                    $sender->sendMessage("§e/spg unlock player §b<player>");
                                    return true;
                                }
                                $this->databaseManager->deletePlayerData($target);
                                $sender->sendMessage("§f-> §aปลดล็อกกล่องทั้งหมดของ§e $target §aแล้ว!");
                                break;
                            default:
                                $sender->sendMessage("§e/cg unlock §cไม่พบคำสั่ง §e$unlockOption");
                                $sender->sendMessage("§e/scg unlock §b<all | player>");
                                return true;
                        }
                        break;
                      
                    default:
                        $sender->sendMessage("§e/scg §cไม่พบคำสั่ง §e$option");
                        $sender->sendMessage("§e/scg §b<unlock>");
                        return true;
                }
                $this->chestGuardLogger->log("[" . $sender->getName() . "] Action:Command Command:" . $command->getName() . " Args:" . implode(",", $args));
                return true;
        }
        return false;
	}
    public function onPlayerBreakBlock(BlockBreakEvent $event) {
        if ($event->getBlock()->getID() === Item::CHEST and $this->databaseManager->isLocked($event->getBlock())) {
            $chest = $event->getBlock();
            $owner = $this->databaseManager->getOwner($chest);
            $attribute = $this->databaseManager->getAttribute($chest);
            $pairChestTile = null;
            if (($tile = $chest->getLevel()->getTile($chest)) instanceof Chest) $pairChestTile = $tile->getPair();
            if ($owner === $event->getPlayer()->getName()) {
                $this->databaseManager->unlock($chest);
                if ($pairChestTile instanceof Chest) $this->databaseManager->unlock($pairChestTile);
                $this->chestGuardLogger->log("[" . $event->getPlayer()->getName() . "] Action:Unlock Level:{$chest->getLevel()->getName()} Coordinate:{$chest->x},{$chest->y},{$chest->z}");
                $event->getPlayer()->sendMessage("§f-> §aปลดล็อกกล่องสำเร็จแล้ว!");
            } elseif ($attribute !== self::NOT_LOCKED and $owner !== $event->getPlayer()->getName() and !$event->getPlayer()->hasPermission("pocketguard.op")) {
                $event->getPlayer()->sendMessage("§f-> §aกล่องนี้ถูกล็อกแล้ว!");
                $event->getPlayer()->sendMessage("§f-> §aลอง \"§e/pg info\" §aเพื่อดูข้อมูลเพิ่มเติมกับกล่องนี้!");
                $this->chestGuardLogger->log("[" . $event->getPlayer()->getName() . "] Action:Unlock Level:{$chest->getLevel()->getName()} Coordinate:{$chest->x},{$chest->y},{$chest->z}");
                $event->setCancelled();
            }
        }
    }
    public function onPlayerBlockPlace(BlockPlaceEvent $event) {
        // Prohibit placing chest next to locked chest
        if ($event->getItem()->getID() === Item::CHEST) {
            $cs = $this->getSideChest($event->getPlayer()->getLevel(), $event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z);
            if (!is_null($cs)) {
                foreach ($cs as $c) {
                    if ($this->databaseManager->isLocked($c)) {
                        $event->getPlayer()->sendMessage("§-> §cไม่สามารถวางกล่องไว้ข้างกล่องที่ล็อกไว้ได้!");
                        $event->setCancelled();
                        return;
                    }
                }
            }
        }
    }
    public function onPlayerInteract(PlayerInteractEvent $event) {
        // Execute task
        if ($event->getBlock()->getID() === Item::CHEST) {
            $chest = $event->getBlock();
            $owner = $this->databaseManager->getOwner($chest);
            $attribute = $this->databaseManager->getAttribute($chest);
            $pairChestTile = null;
            if (($tile = $chest->getLevel()->getTile($chest)) instanceof Chest) $pairChestTile = $tile->getPair();
            if (isset($this->queue[$event->getPlayer()->getName()])) {
                $task = $this->queue[$event->getPlayer()->getName()];
                $taskName = array_shift($task);
                switch ($taskName) {
                    case "lock":
                        if ($attribute === self::NOT_LOCKED) {
                            $this->databaseManager->normalLock($chest, $event->getPlayer()->getName());
                            if ($pairChestTile instanceof Chest) $this->databaseManager->normalLock($pairChestTile, $event->getPlayer()->getName());
                            $event->getPlayer()->sendMessage("§f-> §aล็อกกล่องสำเร็จแล้ว!");
                            $this->chestGuardLogger->log("[" . $event->getPlayer()->getName() . "] Action:Lock Level:{$chest->getLevel()->getName()} Coordinate:{$chest->x},{$chest->y},{$chest->z}");
                        } else {
                            $event->getPlayer()->sendMessage("§f-> §eกล่องนี้ถูกล็อกอยู่แล้ว!");
                        }
                        break;
                    case "unlock":
                        if ($owner === $event->getPlayer()->getName() and $attribute === self::NORMAL_LOCK) {
                            $this->databaseManager->unlock($chest);
                            if ($pairChestTile instanceof Chest) $this->databaseManager->unlock($pairChestTile);
                            $event->getPlayer()->sendMessage("§f-> §aปลดล็อกกล่องสำเร็จแล้ว!");
                            $this->chestGuardLogger->log("[" . $event->getPlayer()->getName() . "] Action:Unlock Level:{$chest->getLevel()->getName()} Coordinate:{$chest->x},{$chest->y},{$chest->z}");
                        } else {
                            $event->getPlayer()->sendMessage("§f-> §eกล่องยังไม่ได้ล็อกในรูปแบบธรรมดา!");
                        }
                        break;
                    case "public":
                        if ($attribute === self::NOT_LOCKED) {
                            $this->databaseManager->publicLock($chest, $event->getPlayer()->getName());
                            if ($pairChestTile instanceof Chest) $this->databaseManager->publicLock($pairChestTile, $event->getPlayer()->getName());
                            $event->getPlayer()->sendMessage("§f-> §aกล่องนี้ทุกคนสามารถเปิดได้แล้ว!");
                            $this->chestGuardLogger->log("[" . $event->getPlayer()->getName() . "] Action:Public Level:{$chest->getLevel()->getName()} Coordinate:{$chest->x},{$chest->y},{$chest->z}");
                        } else {
                            $event->getPlayer()->sendMessage("§f-> §eกล่องนี้ถูกล็อกอยู่แล้ว!");
                        }
                        break;
                    case "info":
                        if ($attribute !== self::NOT_LOCKED) {
                            $message = "§aเจ้าของ§f: $owner \n§aลักษณะการล็อก§f: ";
                            switch ($attribute) {
                                case self::NORMAL_LOCK:
                                    $message .= "§aNormal";
                                    break;
                                case self::PASSCODE_LOCK:
                                    $message .= "§ePasscode";
                                    break;
                                case self::PUBLIC_LOCK:
                                    $message .= "§cPublic";
                                    break;
                            }
                            $event->getPlayer()->sendMessage($message);
                            $this->chestGuardLogger->log("[" . $event->getPlayer()->getName() . "] Action:Info Level:{$chest->getLevel()->getName()} Coordinate:{$chest->x},{$chest->y},{$chest->z}");
                        } else {
                            $event->getPlayer()->sendMessage("§f-> §aกล่องนี้ยังไม่ถูกล็อก!");
                        }
                        break;
                    case "passlock":
                        if ($attribute === self::NOT_LOCKED) {
                            $passcode = array_shift($task);
                            $this->databaseManager->passcodeLock($chest, $event->getPlayer()->getName(), $passcode);
                            if ($pairChestTile instanceof Chest) $this->databaseManager->passcodeLock($pairChestTile, $event->getPlayer()->getName(), $passcode);
                            $event->getPlayer()->sendMessage("§f-> §aล็อกกล่องสำเร็จแล้ว ด้วยรหัส§f \"$passcode\"");
                            $this->chestGuardLogger->log("[" . $event->getPlayer()->getName() . "] Action:Passlock Passcode:$passcode Level:{$chest->getLevel()->getName()} Coordinate:{$chest->x},{$chest->y},{$chest->z}");
                        } else {
                            $event->getPlayer()->sendMessage("§f-> §eกล่องนี้ถูกล็อกอยู่แล้ว!");
                        }
                        break;
                    case "passunlock":
                        if ($attribute === self::PASSCODE_LOCK) {
                            $passcode = array_shift($task);
                            if ($this->databaseManager->checkPasscode($chest, $passcode)) {
                                $this->databaseManager->unlock($chest);
                                if ($pairChestTile instanceof Chest) $this->databaseManager->unlock($pairChestTile);
                                $event->getPlayer()->sendMessage("§f-> §aปลดล็อกกล่องสำเร็จแล้ว!");
                                $this->pocketGuardLogger->log("[" . $event->getPlayer()->getName() . "] Action:Passunlock Level:{$chest->getLevel()->getName()} Coordinate:{$chest->x},{$chest->y},{$chest->z}");
                            } else {
                                $event->getPlayer()->sendMessage("§f-> §cไม่สามารถปลดล็อกกล่องได้ §eเพราะ§f:§cรหัสผ่านผิด!");
                                $this->pocketGuardLogger->log("[" . $event->getPlayer()->getName() . "] Action:FailPassunlock Level:{$chest->getLevel()->getName()} Coordinate:{$chest->x},{$chest->y},{$chest->z}");
                            }
                        } else {
                            $event->getPlayer()->sendMessage("§f-> §eกล่องนี้ยังไม่ถูกล็อกด้วยรหัสผ่าน!");
                        }
                        break;
                    case "share":
                        break;
                }
                $event->setCancelled();
                unset($this->queue[$event->getPlayer()->getName()]);
            } elseif($attribute !== self::NOT_LOCKED and $attribute !== self::PUBLIC_LOCK and $owner !== $event->getPlayer()->getName() and !$event->getPlayer()->hasPermission("pocketguard.op")) {
                $event->getPlayer()->sendMessage("§f-> §eกล่องนี้ถูกล็อกอยู่!");
                $event->getPlayer()->sendMessage("§f-> §aลอง§f \"/pg info\" §aเพื่อดูข้อมูลกล่องเพิ่มเติม!");

                $event->setCancelled();
            }
        }
    }
    private function getSideChest(Level $level, $x, $y, $z)
    {
        $sideChests = [];
        $item = $level->getBlock(new Vector3($x + 1, $y, $z));
        if ($item->getID() === Item::CHEST) $sideChests[] = $item;
        $item = $level->getBlock(new Vector3($x - 1, $y, $z));
        if ($item->getID() === Item::CHEST) $sideChests[] = $item;
        $item = $level->getBlock(new Vector3($x, $y, $z + 1));
        if ($item->getID() === Item::CHEST) $sideChests[] = $item;
        $item = $level->getBlock(new Vector3($x, $y, $z - 1));
        if ($item->getID() === Item::CHEST) $sideChests[] = $item;
        return empty($sideChests) ? null : $sideChests;
    }
}
